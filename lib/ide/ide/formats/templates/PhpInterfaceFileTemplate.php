<?php
namespace ide\formats\templates;

use ide\formats\AbstractFileTemplate;

/**
 * Class PhpInterfaceFileTemplate
 * @package ide\formats\templates
 */
class PhpInterfaceFileTemplate extends AbstractFileTemplate
{
    /**
     * @var string
     */
    protected $interface;

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var string[]
     */
    protected $imports;

    /**
     * PhpInterfaceFileTemplate constructor.
     *
     * @param string $interface
     */
    public function __construct($interface)
    {
        parent::__construct();

        $this->interface = $interface;
        $this->imports = [];
    }

    /**
     * @param string $interface
     */
    public function setInterface($interface)
    {
        $this->interface = $interface;
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
     * @return array
     */
    public function getArguments()
    {
        $header = '';

        if ($this->namespace) {
            $header .= "namespace $this->namespace;\n\n";
        }

        if ($this->imports) {
            foreach ($this->imports as $import) {
                $header .= "use $import;\n";
            }
            $header .= "\n";
        }

        return [
            'INTERFACE' => $this->interface,
            'HEADER'    => $header,
        ];
    }

}
?>
