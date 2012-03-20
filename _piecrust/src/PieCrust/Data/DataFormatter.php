<?php

namespace PieCrust\Data;

use \ArrayAccess;
use \ReflectionClass;
use \ReflectionMethod;
use \ReflectionProperty;


/**
 * A class that formats rendering data to display in a debug window.
 */
class DataFormatter
{
    const MAX_VALUE_LENGTH = 150;
    
    protected $indent;
    
    public function __construct()
    {
        $this->indent = 0;
    }
    
    public function format($data)
    {
        $this->indent++;
        
        $treatAsObject = false;
        if (is_object($data))
        {
            $class = get_class($data);
            $r = new ReflectionClass($class);
            $docComment = $r->getDocComment();
            if (preg_match('/@formatObject/', $docComment))
            {
                $treatAsObject = true;
            }
        }
        
        if (is_array($data) || (!$treatAsObject and is_object($data) and $data instanceof ArrayAccess))
        {
            $this->formatArray($data);
        }
        else if ($treatAsObject || is_object($data))
        {
            $this->formatReflection($data);
        }
        else
        {
            if (is_null($data))
            {
                $strData = "<null>";
            }
            else if (is_bool($data))
            {
                $strData = ($data ? 'true' : 'false');
            }
            else
            {
                $strData = (string)$data;
            }
            
            if (filter_var($strData, FILTER_VALIDATE_URL) !== false)
            {
                // If the value is an URL, turn it into a link.
                echo '<a href="' . $strData . '" style="color: #afa;">' . htmlspecialchars($strData) . '</a>';
            }
            else
            {
                // Truncate the value if it's too long.
                if (strlen($strData) > self::MAX_VALUE_LENGTH)
                    $strData = substr($strData, 0, self::MAX_VALUE_LENGTH - 5) . '[...]';
                echo htmlspecialchars($strData);
            }
        }
        
        $this->indent--;
    }
    
    protected function getIndent()
    {
        return str_repeat('  ', $this->indent);
    }
        
    protected function formatArray($data)
    {
        $includedObjectMethods = array();
        $includedObjectProperties = array();
        if (is_object($data))
        {
            // If this is an object that passes for an array, take some time to
            // look for some documentation string we may need to display.
            $class = get_class($data);
            $r = new ReflectionClass($class);
            
            $docComment = $r->getDocComment();
            $classParams = $this->getReflectionFormattingParameters($docComment);
            if ($classParams['documentation'])
            {
                echo $this->getIndent() . '<span style="' . DataStyles::CSS_DOC . '">' . $classParams['documentation'] . '</span>' . PHP_EOL;
            }

            // See if there are some methods that are explicitely included in 
            // addition to its array contents.
            foreach ($r->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
            {
                $docComment = $method->getDocComment();
                $params = $this->getReflectionFormattingParameters($docComment);
                if ($params['include'])
                    $includedObjectMethods[] = $method;
            }
            foreach ($r->getProperties(ReflectionProperty::IS_PUBLIC) as $property)
            {
                $docComment = $property->getDocComment();
                $params = $this->getReflectionFormattingParameters($docComment);
                if ($params['include'])
                    $includedObjectProperties[] = $property;
            }
        }
        
        // Render the data!
        echo PHP_EOL . $this->getIndent() . '<div style="' . DataStyles::CSS_DATABLOCK . '">' . PHP_EOL;
        $this->indent++;
        // Render any included object methods or properties.
        if (count($includedObjectMethods) > 0 or
            count($includedObjectProperties) > 0)
        {
            foreach ($includedObjectMethods as $method)
            {
                $docComment = $method->getDocComment();
                $params = $this->getReflectionFormattingParameters($docComment);
                $this->formatObjectMethod($data, $method, $params);
            }
            foreach ($includedObjectProperties as $property)
            {
                $docComment = $property->getDocComment();
                $params = $this->getReflectionFormattingParameters($docComment);
                $this->formatObjectProperty($data, $property, $params);
            }
        }
        // Render the formatted array contents.
        foreach ($data as $key => $value)
        {
            echo $this->getIndent() . '<div style="' . DataStyles::CSS_VALUE . '">' . $key;
            if ($value !== null)
            {
                echo ' : ';
                $this->format($value);
            }
            echo $this->getIndent() . '</div>' . PHP_EOL;
        }
        $this->indent--;
        echo $this->getIndent() . '</div>' . PHP_EOL;
    }
    
    protected function formatReflection($data)
    {
        $class = get_class($data);
        $r = new ReflectionClass($class);
        
        // Display an optional documentation string.
        $docComment = $r->getDocComment();
        $classParams = $this->getReflectionFormattingParameters($docComment);
        if ($classParams['documentation'])
        {
            echo $this->getIndent() . '<span style="' . DataStyles::CSS_DOC . '">' . $classParams['documentation'] . '</span>' . PHP_EOL;
        }
        
        // Start inspecting the object's methods and properties, and format
        // them along the way.
        echo PHP_EOL . $this->getIndent() . '<div style="' . DataStyles::CSS_DATABLOCK . '">' . PHP_EOL;
        $this->indent++;
        
        foreach ($r->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
        {
            // Filter special methods out.
            if ($method->isConstructor() or
                $method->isDestructor())
                continue;

            // Filter ignored or non-explicitely-included methods.
            $docComment = $method->getDocComment();
            $params = $this->getReflectionFormattingParameters($docComment);
            if ($params['ignore'])
                continue;
            if ($classParams['explicitInclude'] and !$params['include'])
                continue;
            
            $this->formatObjectMethod($data, $method, $params);
        }
        
        foreach ($r->getProperties(ReflectionProperty::IS_PUBLIC) as $property)
        {
            $docComment = $property->getDocComment();
            $params = $this->getReflectionFormattingParameters($docComment);
            if ($params['ignore'])
                continue;
            if ($classParams['explicitInclude'] and !$params['include'])
                continue;
            
            $this->formatObjectProperty($data, $property, $params);
        }
        
        $this->indent--;
        
        echo $this->getIndent() . '</div>' . PHP_EOL;
    }

    protected function formatObjectMethod($data, $method, $params)
    {
        // If this method can be called without arguments, and there's no
        // '@noCall' annotation on it, get the value so we can display it.
        // Otherwise, display only the method's signature.
        $value = null;
        $argCount = $method->getNumberOfRequiredParameters();
        if ($argCount == 0)
        {
            $name = strtolower($method->getName());
            if(!$params['noCall'])
            {
                $value = $method->invoke($data);
            }
        }
        else
        {
            $name = strtolower($method->getName()) . '(';
            $args = $method->getParameters();
            $firstArg = true;
            foreach ($args as $a)
            {
                if (!$firstArg)
                    $name .= ', ';
                $firstArg = false;

                $name .= $a->getName();
            }
            $name .= ')';
        }

        echo $this->getIndent() . '<div style="' . DataStyles::CSS_VALUE . '">' . $name;
        if ($params['documentation'])
        {
            echo ' <span style="' . DataStyles::CSS_DOC . '">&ndash; ' . $params['documentation'] . '</span>' . PHP_EOL;
        }
        if ($value !== null)
        {
            echo ' : ' . PHP_EOL;
            $this->format($value);
        }
        echo $this->getIndent() . '</div>' . PHP_EOL;
    }

    protected function formatObjectProperty($data, $property, $params)
    {
        $name = $property->getName();

        // Only get the value of this property if there's no '@noCall'
        // annotation on it.
        $value = null;
        if (!$params['noCall'])
        {
            $value = $property->getValue($data);
        }

        echo $this->getIndent() . '<div style="' . DataStyles::CSS_VALUE . '">' . $name;
        if ($params['documentation'])
        {
            echo ' <span style="' . DataStyles::CSS_DOC . '">' . $params['documentation'] . '</span>' . PHP_EOL;
        }
        if ($value !== null)
        {
            echo ' : ' . PHP_EOL;
            $this->format($value);
        }
        echo $this->getIndent() . '</div>' . PHP_EOL;
    }
    
    protected function getReflectionFormattingParameters($docComments)
    {
        $params = array(
            'ignore' => false,
            'include' => false,
            'documentation' => null,
            'explicitInclude' => false,
            'noCall' => false
        );
        if (preg_match('/@ignore/', $docComments))
        {
            // Don't include this property/method.
            $params['ignore'] = true;
        }
        if (preg_match('/@include/', $docComments))
        {
            // Do include this property/method (for use with '@explicitInclude').
            $params['include'] = true;
        }
        if (preg_match('/@explicitInclude/', $docComments))
        {
            // Declare that only properties/methods with '@include' should be
            // formatted.
            $params['explicitInclude'] = true;
        }
        if (preg_match('/@noCall/', $docComments))
        {
            // Don't call into a method, or don't get a property's value.
            $params['noCall'] = true;
        }
        $m = array();
        if (preg_match('/@documentation\s+(.*)/m', $docComments, $m))
        {
            // A documentation string to display.
            $params['documentation'] = $m[1];
        }
        return $params;
    }
}
