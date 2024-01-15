<?php
namespace ide\project;

use Exception;
use ide\Ide;
use ide\Logger;
use ide\systems\FileSystem;
use ide\systems\IdeSystem;
use ide\utils\FileUtils;
use php\format\ProcessorException;
use php\io\FileStream;
use php\io\IOException;
use php\io\Stream;
use php\lang\System;
use php\lib\arr;
use php\lib\fs;
use php\lib\Str;
use php\time\Time;
use php\util\Scanner;
use php\xml\DomDocument;
use php\xml\DomElement;
use php\xml\XmlProcessor;
use php\io\File;

/**
 *  .dnproject
 *
 * Class ProjectConfig
 * @package ide\project
 */
class ProjectConfig
{
    /**
     * @var string
     */
    protected $rootDir;

    /**
     * @var string
     */
    protected $projectName;

    /**
     * @var DomDocument
     */
    protected $document;

    /**
     * @var XmlProcessor
     */
    protected $processor;

    /**
     * @var string
     */
    protected $configPath;

    /**
     * ProjectConfig constructor.
     *
     * @param string $rootDir
     * @param string $projectName
     */
    public function __construct($rootDir, $projectName)
    {
        $this->processor = new XmlProcessor();

        $this->rootDir = $rootDir;
        $this->projectName = $projectName;

        $this->configPath = $configPath = "$rootDir/$projectName.dnproject";

        $this->reload();
        $this->update(true);
    }

    /**
     * @param string $filename
     *
     * @return ProjectConfig
     */
    public static function createForFile($filename)
    {
        $file = File::of($filename);

        $name = $file->getName();

        if (Str::endsWith($name, '.dnproject')) {
            $name = Str::sub($name, 0, Str::length($name) - 10);
        }

        return new ProjectConfig($file->getParent(), $name);
    }

    public function save()
    {
        $this->update();

        $parentFile = File::of($this->configPath)->getParentFile();

        if ($parentFile) {
            $parentFile->mkdirs();
        }

        $file = new File($this->configPath);

        if (!$file->exists()) {
            $file->createNewFile(true);
        }

        FileUtils::put($this->configPath, $this->processor->format($this->document));

        $configFile = File::of($this->configPath);
        $newConfigFile = File::of($this->configPath . '.tmp');


        Stream::tryAccess($this->configPath, function (Stream $stream) use ($newConfigFile) {
            try {
                $newConfig = new FileStream($newConfigFile, 'w+');

                $scanner = new Scanner($stream, 'UTF-8');

                while ($scanner->hasNextLine()) {
                    $line = $scanner->nextLine();

                    if (str::trim($line)) {
                        $newConfig->write($line . "\r\n");
                    }
                }

                $newConfig->close();
            } catch (IOException $e) {
                Logger::warn("Unable to trim project config, {$e->getMessage()}");
            }
        });

        if ($configFile->delete()) {
            $newConfigFile->renameTo($configFile);
        } else {
            Logger::warn("Unable to trim project config, cannot rename tmp file to origin");
            $configFile->delete();
        }
    }

    public function reload()
    {
        if (File::of($this->configPath)->isFile()) {
            try {
                $this->document = $this->processor->parse(Stream::getContents($this->configPath));
                $this->validate();
            } catch (ProcessorException $e) {
                $this->document = $this->processor->createDocument();
            }
        } else {
            $this->document = $this->processor->createDocument();
        }
    }

    /**
     * @return string
     */
    public function getConfigPath()
    {
        return $this->configPath;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function getProperty($name)
    {
        return $this->document->get("/project/@$name");
    }

    /**
     * @param string $name
     * @param string $value
     */
    public function setProperty($name, $value)
    {
        $this->document->getDocumentElement()->setAttribute($name, $value);
    }

    /**
     * @return string
     */
    public function getPackageName()
    {
        return $this->getProperty('packageName') ?: 'app';
    }

    /**
     * @return null|Time
     */
    public function getCreatedAt()
    {
        $createdAt = $this->getProperty('createdAt');

        return $createdAt ? new Time($createdAt) : null;
    }

    /**
     * @return null|Time
     */
    public function getUpdatedAt()
    {
        $updatedAt = $this->getProperty('updatedAt');

        return $updatedAt ? new Time($updatedAt) : null;
    }

    /**
     * @return string
     */
    public function getAuthor()
    {
        return $this->getProperty('author');
    }

    /**
     * @return string
     */
    public function getAuthorOS()
    {
        return $this->getProperty('authorOS');
    }

    /**
     * @return string
     */
    public function getIdeVersion()
    {
        return $this->getProperty('ideVersion');
    }

    /**
     * @return int
     */
    public function getIdeVersionHash()
    {
        return (int)$this->getProperty('ideVersionHash');
    }

    /**
     * @return string
     */
    public function getIdeName()
    {
        return $this->getProperty('ideName');
    }

    /**
     * @return AbstractProjectTemplate
     */
    public function getTemplate()
    {
        $templateClass = $this->getProperty('template');

        if (class_exists($templateClass)) {
            return new $templateClass();
        }

        return null;
    }

    /**
     * @param ProjectFile[] $files
     */
    public function setProjectFiles(array $files)
    {
        $domFiles = $this->document->find('/project/files');

        if (!$domFiles) {
            $domFiles = $this->document->createElement('files');
            $this->document->find('/project')->appendChild($domFiles);
        }

        foreach ($domFiles->findAll('file') as $domFile) {
            $domFiles->removeChild($domFile);
        }

        /*foreach ($files as $file) {
            $domFile = $this->document->createElement('file');
            $file->serialize($domFile, $this->document);

            $domFiles->appendChild($domFile);
        }*/
    }

    public function loadTreeState(ProjectTree $tree)
    {
        if ($this->document->find('/project/tree')) {
            $paths = [];

            /** @var DomElement $domOne */
            foreach ($this->document->findAll('/project/tree/expanded/path') as $domOne) {
                $path = $domOne->getAttribute('src');
                $paths[$path] = $path;
            }

            $tree->setExpandedPaths($paths);
        }
    }

    /**
     * @param ProjectTree|array $treeOrPaths
     */
    public function setTreeState($treeOrPaths)
    {
        $domTree = $this->document->find('/project/tree');

        if (!$domTree) {
            $domTree = $this->document->createElement('tree');
            $this->document->find('/project')->appendChild($domTree);
        }

        $domExpanded = $domTree->find('expanded');

        if (!$domExpanded) {
            $domExpanded = $this->document->createElement('expanded');
            $domTree->appendChild($domExpanded);
        }

        foreach ($domExpanded->findAll('path') as $domOne) {
            $domExpanded->removeChild($domOne);
        }

        $paths = $treeOrPaths;

        if ($treeOrPaths instanceof ProjectTree) {
            $paths = $treeOrPaths->getExpandedPaths();
        }

        foreach ((array)$paths as $path) {
            $domPath = $this->document->createElement('path', ['@src' => $path]);
            $domExpanded->appendChild($domPath);
        }
    }

    public function setOpenedFiles(array $files, $selectedFile, array $windowFiles = [])
    {
        $domOpenedFiles = $this->document->find('/project/openedFiles');

        if (!$domOpenedFiles) {
            $domOpenedFiles = $this->document->createElement('openedFiles');
            $this->document->find('/project')->appendChild($domOpenedFiles);
        }

        foreach ($domOpenedFiles->findAll('file') as $domOpenedFile) {
            $domOpenedFiles->removeChild($domOpenedFile);
        }

        $windowFiles = arr::combine($windowFiles, $windowFiles);

        foreach ($files as $file) {
            $domFile = $this->document->createElement('file');
            $domFile->setAttribute('src', $file instanceof ProjectFile ? $file->getRelativePath() : $file);

            if (FileUtils::hashName($selectedFile) == FileUtils::hashName($file)) {
                $domFile->setAttribute('selected', '1');
            }

            if ($windowFiles[FileUtils::hashName($file)]) {
                $domFile->setAttribute('window', '1');
            }

            $domOpenedFiles->appendChild($domFile);
        }
    }

    /**
     * @param AbstractProjectBehaviour[] $behaviours
     */
    public function setBehaviours(array $behaviours)
    {
        $domBehaviours = $this->document->find('/project/behaviours');

        if (!$domBehaviours) {
            $domBehaviours = $this->document->createElement('behaviours');
            $this->document->find('/project')->appendChild($domBehaviours);
        }

        foreach ($domBehaviours->findAll('behaviour') as $domBehavior) {
            $domBehaviours->removeChild($domBehavior);
        }

        foreach ($behaviours as $behaviour) {
            $domBehavior = $this->document->createElement('behaviour');
            $domBehavior->setAttribute('class', get_class($behaviour));

            $behaviour->serialize($domBehavior, $this->document);

            $domBehaviours->appendChild($domBehavior);
        }
    }

    /**
     * @param Project $project
     *
     * @return ProjectFile[]
     */
    public function createFiles(Project $project)
    {
        $files = [];

        /** @var DomElement $domFile */
        /*foreach ($this->document->findAll('/project/files/file') as $domFile) {
            $projectFile = ProjectFile::unserialize($project, $domFile);
            $files[$projectFile->getNameHash()] = $projectFile;
        }    */

        return $files;
    }

    /**
     * @return array
     */
    public function getOpenedFiles()
    {
        $files = [];

        /** @var DomElement $file */
        foreach ($this->document->findAll('/project/openedFiles/file') as $file) {
            if (!$file->getAttribute('window')) {
                $files[] = $file->getAttribute('src');
            }
        }

        return $files;
    }

    /**
     * @return array
     */
    public function getWindowOpenedFiles()
    {
        $files = [];

        /** @var DomElement $file */
        foreach ($this->document->findAll('/project/openedFiles/file') as $file) {
            if ($file->getAttribute('window')) {
                $files[] = $file->getAttribute('src');
            }
        }

        return $files;
    }

    /**
     * @return null|string
     */
    public function getSelectedFile()
    {
        /** @var DomElement $file */
        foreach ($this->document->findAll('/project/openedFiles/file') as $file) {
            if ($file->getAttribute('selected')) {
                return $file->getAttribute('src');
            }
        }

        return null;
    }

    /**
     * @param Project $project
     * @return AbstractProjectBehaviour[]
     * @throws InvalidProjectFormatException
     */
    public function createBehaviours(Project $project)
    {
        $behaviours = [];

        /** @var DomElement $domBehaviour */
        foreach ($this->document->findAll('/project/behaviours/behaviour') as $domBehaviour) {
            $class = $domBehaviour->getAttribute('class');

            if (class_exists($class)) {
                $behaviour = new $class();

                if ($behaviour instanceof AbstractProjectBehaviour) {
                    $behaviours[get_class($behaviour)] = $behaviour;
                }
            } else {
                Logger::error("Unable add project behaviour, class '$class' is not found.");
            }
        }

        foreach ($behaviours as $class => $behaviour) {
            $behaviour = $project->register($behaviour, false);
            $behaviour->unserialize($domBehaviour);

            $behaviours[$class] = $behaviour;
        }

        return $behaviours;
    }

    protected function update($onlyNew = false)
    {
        $project = $this->document->find('/project');

        if (!$project) {
            $project = $this->document->createElement('project');
            $this->document->appendChild($project);

            $project->setAttribute('author', System::getProperty('user.name'));
            $project->setAttribute('authorOS', System::getProperty('os.name'));

            $project->setAttribute('createdAt', Time::millis());
        }

        if (!$onlyNew) {
            $project->setAttribute('ideVersion', Ide::get()->getVersion());
            $project->setAttribute('ideVersionHash', Ide::get()->getConfig()->get('app.hash'));
            $project->setAttribute('ideName', Ide::get()->getName());
            $project->setAttribute('updatedAt', Time::millis());
        }
    }

    /**
     * @throws Exception
     */
    protected function validate()
    {
        if (!$this->document->find('/project')) {
            throw new ProcessorException("Invalid project configuration!");
        }
    }

    /**
     * @param Project $project
     */
    public function setProject(Project $project)
    {
        /** @var DomElement $domProject */
        $domProject = $this->document->find('/project');

        $domProject->setAttribute('name', $project->getName());
        $domProject->setAttribute('template', get_class($project->getTemplate()));
        $domProject->setAttribute('packageName', $project->getPackageName());
    }
}