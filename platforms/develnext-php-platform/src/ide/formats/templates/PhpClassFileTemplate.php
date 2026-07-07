<?php
namespace ide\formats\templates;

use ide\formats\AbstractFileTemplate;
use php\lib\str;

/**
 * Class PhpClassFileTemplate
 * @package ide\formats\templates
 */
class PhpClassFileTemplate extends AbstractFileTemplate
{
    /**
     * @var string
     */
    protected $class;

    /**
     * @var string
     */
    protected $extends;

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var string[]
     */
    protected $imports;

    /**
     * @var string
     */
    protected $phpdoc;

    /**
     * @var string
     */
    protected $structureType = 'class';

    /**
     * PhpClassFileTemplate constructor.
     *
     * @param $class
     * @param $extends
     */
    public function __construct($class, $extends)
    {
        parent::__construct();

        $this->class = $class;
        $this->extends = $extends;
    }

    /**
     * @param string $class
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * @param string $extends
     */
    public function setExtends($extends)
    {
        $this->extends = $extends;
    }

    /**
     * @param string $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @param string[] $imports
     */
    public function setImports($imports)
    {
        $this->imports = $imports;
    }

    /**
     * @return string
     */
    public function getPhpdoc(): string
    {
        return $this->phpdoc;
    }

    /**
     * @param string $phpdoc
     */
    public function setPhpdoc(string $phpdoc)
    {
        $this->phpdoc = $phpdoc;
    }

    /**
     * @param string $structureType class|interface|trait|abstract|final
     */
    public function setStructureType($structureType)
    {
        $this->structureType = $structureType;
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        $header = '';

        if ($this->namespace) {
            $header .= "namespace $this->namespace;";
        }

        if ($this->imports) {
            $header .= "\n\n";
        }

        foreach ($this->imports as $import) {
            $header .= "use $import;\n";
        }

        $phpdoc = "";
        if ($this->phpdoc) {
            $lines = str::lines($this->phpdoc);
            foreach ($lines as $i => $line) {
                $lines[$i] = " * $line";
            }

            $phpdoc = "/**\n" . str::join($lines, "\n") . "\n */";
        }

        return [
            'CLASS'     => $this->class,
            'HEADER'    => $header,
            'TYPE'      => $this->resolveStructureKeyword(),
            'EXTENDS'   => $this->extends ? "extends $this->extends" : "",
            'PHPDOC'    => $phpdoc
        ];
    }

    protected function resolveStructureKeyword()
    {
        switch ($this->structureType) {
            case 'interface':
                return 'interface';
            case 'trait':
                return 'trait';
            case 'abstract':
                return 'abstract class';
            case 'final':
                return 'final class';
            default:
                return 'class';
        }
    }
}