<?php

namespace ARUSH;

class Autoloader {
    public function __construct() {
        spl_autoload_register(array($this, 'loadClass'));
    }

    public function loadClass($className) {
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
        if (file_exists($file)) {
            require_once($file);
        } elseif (trait_exists($className)) {
            // If it's a trait, attempt to load it
            require_once(__DIR__ . '/' . $file); // Update the path as needed
        } elseif (str_replace('ARUSH/', '', $file)) {
            require_once str_replace('ARUSH/', '', $file);
        }
    }
}

// Initialize the autoloader
$autoloader = new Autoloader();
