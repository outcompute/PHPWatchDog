<?php
use OutCompute\PHPWatchDog as PHPWatchDog;

function func1() {
    return true;
}
function func2() {
    return true;
}
function func3() {
    return true;
}

class MainTest extends PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        PHPWatchDog\Main::configure(array(
            'functions' => array(
                'func1()' => array(
                    'default' => 'block' # Block func1() everywhere
                ),
                'ClassA->blockedMethod()' => array(
                    'default' => 'block' # Block blockedMethod in ClassA everywhere
                ),
                'func2()' => array(
                    'default' => 'block', # Block func2() everywhere, except ...
                    'except' => array(
                        array(
                            'scope' => 'ClassB->canCallFunc2()' # ... in canCallFunc2() in ClassB, where it can be called.
                        )
                    )
                ),
                'func3()' => array(
                    'default' => 'block', # Block func3() everywhere, except ...
                    'except' => array(
                        array(
                            'file' => 'ClassC.php', # ... in file ClassC.php ...
                            'scope' => 'ClassC->canCallFunc3()' # ... and the method canCallFunc3() in class ClassC
                        )
                    )
                )
            ),
            'files' => array(
                'fileForE.file' => array(
                    'default' => 'block', # Block access (read+write) to fileForE.file everywhere, except ...
                    'except' => array(
                        array(
                            'scope' => 'ClassE->canWriteToFile()' # ... canWriteToFile() in ClassE which can access it.
                        )
                    )
                )
            )
        ), false);
    }

    /**
     * @expectedException Exception
     */
    public function testDisablingAOP() {
        ini_set('aop.enable', 0);
    }

    /**
     * @expectedException Exception
     */
    public function testRedefiningWatchlist() {
        PHPWatchDog\Main::configure(array(
            'functions' => array(
                'func1()' => array(
                    'default' => 'allow'
                )
            )
        ), false);
    }


    /**
     * @expectedException Exception
     */
    public function testBlockedPHPFunction() {
        $a = new ClassA();
        $a->cannotCallFunc1();
    }

    /**
     * @expectedException Exception
     */
    public function testBlockedClassMethod() {
        $a = new ClassA();
        $a->blockedMethod();
    }


    /**
     * @expectedException Exception
     */
    public function testAnotherBlockedPHPFunction() {
        $a = new ClassA();
        $a->cannotCallFunc2();
    }

    public function testAllowedPHPFunction() {
        $b = new ClassB();
        $this->assertTrue($b->canCallFunc2());
    }


    /**
     * @expectedException Exception
     */
    public function testPHPFunctionBlockedInThisMethod() {
        $c = new ClassC();
        $c->cannotCallFunc3();
    }

    public function testPHPFunctionAllowedInThisMethod() {
        $c = new ClassC();
        $this->assertTrue($c->canCallFunc3());
    }


    /**
     * @expectedException Exception
     */
    public function testWriteBlocked() {
        $d = new ClassD();
        $d->cannotWriteToFile(dirname(__FILE__).'/fileForE.file');
    }

    public function testWriteAllowed() {
        $e = new ClassE();
        $this->assertTrue($e->canWriteToFile(dirname(__FILE__).'/fileForE.file'));
    }
}

?>
