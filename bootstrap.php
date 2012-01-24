<?php
    if (!class_exists('CSRFProtection')) {
        class CSRFProtection {
            public static function tokenTag() { return ''; }
        }
    }

    if (!class_exists('SkipLinks')) {
        class SkipLinks {
            public static function addIndex($name, $id) {}
            public static function addLink($name, $id) {}
        }
    }
    
