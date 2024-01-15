<?php
namespace ide\project\behaviours;

use ide\misc\GradleBuildConfig;
use ide\project\AbstractProjectBehaviour;
use ide\project\ProjectExporter;
use ide\utils\FileUtils;
use php\io\File;

/**
 * Class GradleProjectBehaviour
 * @package ide\project\behaviours
 */
class GradleProjectBehaviour extends AbstractProjectBehaviour
{
    /**
     * @var GradleBuildConfig
     */
    protected $config;

    /**
     * ...
     */
    public function inject()
    {
        $this->config = new GradleBuildConfig($this->project->getRootDir() . "/build.gradle");

        $this->project->on('save', [$this, 'doSave']);
        $this->project->on('export', [$this, 'doExport']);
        $this->project->on('open', [$this, 'doOpen']);
        $this->project->on('close', [$this, 'doClose']);
    }

    public function doOpen()
    {
        FileUtils::deleteDirectory($this->project->getFile("build/"));
        //$this->config->startDaemon();
    }

    public function doClose()
    {
        //$this->config->stopDaemon();
    }

    public function doExport(ProjectExporter $exporter)
    {
        $exporter->addFile($this->project->getFile("build.xml"));
    }

    public function doSave()
    {
        $this->config->setProjectName($this->project->getName());
        $this->config->save();
    }

    public function addDependency($artifactId, $group = null, $version = null)
    {
        $this->config->setDependency($artifactId, $group, $version);
    }

    public function removeDependency($artifactId, $group = null)
    {
        $this->config->removeDependency($artifactId, $group);
    }

    public function addRepository($name, $value = null)
    {
        $this->config->addRepository($name, $value);
    }

    public function addJcenterRepository()
    {
        $this->addRepository('jcenter');
    }

    public function addMavenCentralRepository()
    {
        $this->addRepository('mavenCentral');
    }

    public function addMavenLocalRepository()
    {
        $this->addRepository('mavenLocal');
    }

    public function addLocalLibRepository()
    {
        $this->addRepository('local', new File("lib/"));
    }

    /**
     * @return GradleBuildConfig
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * see PRIORITY_* constants
     * @return int
     */
    public function getPriority()
    {
        return self::PRIORITY_COMPONENT;
    }
}