<?php
namespace ide\project;


interface ProjectConsoleOutput
{
    function addConsoleLine($line, $color = '#000000');
}