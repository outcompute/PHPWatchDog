<?php
class ClassA {
    function __construct() {
    }
    function cannotCallFunc1() {
        func1();
        return true;
    }
    function cannotCallFunc2() {
        func2();
        return true;
    }
    function blockedMethod() {
        return true;
    }
}

?>
