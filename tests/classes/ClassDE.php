<?php
class ClassD {
    function __construct() {}

    function cannotWriteToFile($filename) {
        $h = fopen($filename, 'w+');
        fwrite($h, "D wrote: ".date("H:i:s", time()));
        fclose($h);
        return true;
    }
}

class ClassE {
    function __construct() {}

    function canWriteToFile($filename) {
        $h = fopen($filename, 'w+');
        fwrite($h, "E wrote: ".date("H:i:s", time()));
        fclose($h);
        return true;
    }
}

?>
