<?php

namespace PieCrust\Baker;


/**
 * A node in a bake tree.
 */
class ProcessingTreeNode
{
    // Dirty states {{{
    const STATE_UNKNOWN = 0;
    const STATE_DIRTY = 1;
    const STATE_CLEAN = 2;
    // }}}

    protected $path;
    /**
     * Gets the path of the file to process.
     */
    public function getPath()
    {
        return $this->path;
    }

    protected $availableProcessors;
    /**
     * Gets the processors available at this level in the tree.
     */
    public function getAvailableProcessors()
    {
        $this->ensureProcessor();
        return $this->availableProcessors;
    }

    protected $processor;
    /**
     * Gets the matching processor for the file.
     */
    public function getProcessor()
    {
        $this->ensureProcessor();
        return $this->processor;
    }

    protected $outputs;
    /**
     * Gets the output nodes.
     */
    public function getOutputs()
    {
        return $this->outputs;
    }

    /**
     * Adds an output node.
     */
    public function addOutput(ProcessingTreeNode $node)
    {
        $this->outputs[] = $node;
    }

    protected $level;
    /**
     * Gets the level of this node in the tree.
     */
    public function getLevel()
    {
        return $this->level;
    }

    protected $state;
    /**
     * Gets the dirty state of this node.
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Sets the dirty state of this node.
     */
    public function setState($state, $recursive = false)
    {
        $this->state = $state;

        if ($recursive)
        {
            foreach ($this->outputs as $output)
            {
                $output->setState($state, true);
            }
        }
    }

    /**
     * Creates a new instance of ProcessNode.
     */
    public function __construct($path, array $availableProcessors, $level = 0)
    {
        $this->path = $path;
        $this->availableProcessors = $availableProcessors;
        $this->processor = null;
        $this->outputs = array();
        $this->level = $level;
        $this->state = self::STATE_UNKNOWN;
    }

    /**
     * Gets the list of leaf nodes in this sub-tree.
     */
    public function getLeaves()
    {
        if ($this->isLeaf())
        {
            return array($this);
        }
        else
        {
            $leaves = array();
            foreach ($this->outputs as $output)
            {
                foreach ($output->getLeaves() as $leaf)
                {
                    $leaves[] = $leaf;
                }
            }
            return $leaves;
        }
    }

    /**
     * Returns whether this node is a leaf.
     */
    public function isLeaf()
    {
        return count($this->outputs) == 0;
    }

    protected function ensureProcessor()
    {
        if ($this->processor !== null)
            return;

        $this->processor = false;
        $extension = pathinfo($this->path, PATHINFO_EXTENSION);
        foreach ($this->availableProcessors as $index => $processor)
        {
            if ($processor->supportsExtension($extension))
            {
                $this->processor = $processor;
                // Take that processor out of the list to prevent
                // loops in the tree.
                unset($this->availableProcessors[$index]);
                break;
            }
        }
    }
}

