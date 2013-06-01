<?php

namespace PieCrust\Data;


class DataStyles
{
    /**
     * The debug DIV itself.
     */
    const CSS_DEBUGINFO = <<<CSSCODE
text-align: left;
font-style: normal;
padding: 1em;
background: #a42;
color: #fff;
position: fixed;
width: 50%;
bottom: 0;
right: 0;
overflow: auto;
max-height: 50%;
box-shadow: 0 0 10px #633;
CSSCODE;

    /**
     * HTML elements.
     */
    const CSS_P = 'margin: 0; padding: 0;';
    const CSS_A = 'color: #fff; text-decoration: none;';
    
    /**
     * Headers.
     */
    const CSS_BIGHEADER = 'margin: 0.5em 0; font-weight: bold;';
    const CSS_HEADER = 'margin: 0.5em 0; font-weight: bold;';
    
    /**
     * Data block elements.
     */
    const CSS_DATA = 'font-family: Courier, sans-serif; font-size: 0.9em;';
    const CSS_DATABLOCK = 'margin-left: 2em;';
    const CSS_VALUE = '';
    const CSS_DOC = 'color: #fa8; font-size: 0.9em;';
}
