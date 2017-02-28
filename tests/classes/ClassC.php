<?php
class ClassC {
    function __construct() {}

    function cannotCallFunc3() {
        func3();
        return true;
    }

    function canCallFunc3() {
        func3();
        return true;
    }
}
?>
