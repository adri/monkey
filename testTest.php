<?php
require_once 'vendor/autoload.php';

class MyNodeVisitor extends PhpParser\NodeVisitorAbstract
{
    protected $proxyClass;

    public function __construct($proxyClass)
    {
        $this->proxyClass = $proxyClass;
    }

    public function leaveNode(PhpParser\Node $node) {
        if ($node instanceof PhpParser\Node\Expr\FuncCall && $node->name->isUnqualified()) {

            $replacement = new \PhpParser\Node\Name\FullyQualified(array());
            $replacement->set($this->proxyClass . '::' . (string) $node->name);

            $node->name = $replacement;
        }
    }
}

/**
 * Takes specified file and parses it to replace all function calls with
 * our own implementation.
 *
 * This allows for testing procedural code.
 */
class Monkey
{
    protected $file;
    protected $parser;
    protected $mocks = array();
    protected static $instances = array();

    protected function __construct($file, $parser)
    {
        $this->file = $file;
        $this->parser = $parser;
        $this->proxyClass = 'MonkeyPatcher' . rand();
    }

    /**
     * Factory method to create a new monkey patcher for specified file.
     *
     * @param $file
     */
    public static function patch($file) {
        $parser = new PhpParser\Parser(new PhpParser\Lexer);

        $monkey = new Monkey($file, $parser);
        $monkey->replace();

        self::$instances[$monkey->proxyClass] = $monkey;

        return $monkey;
    }

    public static function call($instanceName, $function, array $arguments)
    {
        $monkey = self::$instances[$instanceName];

        if (isset($monkey->mocks[$function])) {
            return $monkey->mocks[$function];
        }

        return call_user_func_array($function, $arguments);
    }

    public function replace()
    {
        $this->generateProxyClass();
        $code = $this->replaceFunctionCalls(file_get_contents($this->file));
        eval($code);
    }

    public function mock($function, $returnValue)
    {
        $this->mocks[$function] = $returnValue;
    }

    public function reset()
    {
       $this->mocks = array();
    }

    private function replaceFunctionCalls($code)
    {
        $traverser = new PhpParser\NodeTraverser;
        $traverser->addVisitor(new MyNodeVisitor($this->proxyClass));

        try {
            $ast = $this->parser->parse($code);
            $ast = $traverser->traverse($ast);
        } catch (PhpParser\Error $e) {
            echo 'Parse Error: ', $e->getMessage();
        }

        $prettyPrinter = new PhpParser\PrettyPrinter\Standard;
        return $prettyPrinter->prettyPrint($ast);
    }


    private function generateProxyClass()
    {
        eval("
            class {$this->proxyClass}
            {
                public static function __callStatic(\$function, array \$arguments)
                {
                    return Monkey::call('{$this->proxyClass}', \$function, \$arguments);
                }
            }
        ");
    }
}

class ExampleTest extends \PHPUnit_Framework_TestCase
{
    protected static $monkey;

    public static function setupBeforeClass()
    {
        self::$monkey = Monkey::patch('test.php');
    }

    public function tearDown()
    {
        self::$monkey->reset();
    }

    /**
     * @dataProvider goodMorningTranslationProvider
     * @param $expected
     * @param $language
     * @param string $name
     */
    public function testGoodMorningTranslations($expected, $language, $name = 'Els')
    {
        self::$monkey->mock('current_language', $language);
        $actual = goodmorning($name);
        $this->assertEquals($expected, $actual);
    }

    public function  goodMorningTranslationProvider() {
        return array(
            array( 'Good morning Els', 'en'),
            array( 'Guten Morgen Els', 'de'),
            array( 'Goedemorgen Els', 'nl'),
        );
    }
}




