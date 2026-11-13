<?php

namespace InversionesJL22Site\Vendor\FontLib\Exception;

class FontNotFoundException extends \Exception
{
    public function __construct($fontPath)
    {
        $this->message = 'Font not found in: ' . $fontPath;
    }
}