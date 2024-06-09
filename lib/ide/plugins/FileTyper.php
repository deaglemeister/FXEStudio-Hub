<?php

use php\gui\UXImage;
use php\io\File;
use php\lib\fs;

use platform\plugins\FXEPlugin;
use platform\plugins\traits\FileTypes;
use platform\types\FileType;

return new class extends FXEPlugin
{
    public function getName(): string
    {
        return 'FileTyper';
    }
    public function getDescription(): string
    {
        return '';
    }
    public function getVersion(): float
    {
        return 1.0;
    }
    public function getAuthor(): string
    {
        return 'mafujo';
    }


    use FileTypes;

    public function getFileTypes(): array
    {
        return [
            new class extends FileType
            {
                public function validate(File $file): bool
                {
                    return in_array(fs::ext($file), ['zip', 'rar', 'jar', 'tar']);
                }

                public function getIcon(): UXImage
                {
                    return new UXImage('res://resources/expui/icons/fileTypes/archive_dark.png');
                }
            },
            new class extends FileType
            {
                public function validate(File $file): bool
                {
                    return in_array(fs::ext($file), ['conf', 'config']);
                }

                public function getIcon(): UXImage
                {
                    return new UXImage('res://resources/expui/icons/fileTypes/config_dark.png');
                }
            },
            new class extends FileType
            {
                public function validate(File $file): bool
                {
                    return in_array(fs::ext($file), ['csv']);
                }

                public function getIcon(): UXImage
                {
                    return new UXImage('res://resources/expui/icons/fileTypes/csv_dark.png');
                }
            },

            new class extends FileType
            {
                public function validate(File $file): bool
                {
                    return in_array(fs::ext($file), ['ttf', 'woff', 'otf', 'eot']);
                }

                public function getIcon(): UXImage
                {
                    return new UXImage('res://resources/expui/icons/fileTypes/font_dark.png');
                }
            },
            new class extends FileType
            {
                public function validate(File $file): bool
                {
                    return in_array(fs::ext($file), ['gitignore']);
                }

                public function getIcon(): UXImage
                {
                    return new UXImage('res://resources/expui/icons/fileTypes/gitignore.png');
                }
            },
            new class extends FileType
            {
                public function validate(File $file): bool
                {
                    return in_array(fs::ext($file), ['svg', 'png', 'jpg', 'jpeg', 'gif']);
                }

                public function getIcon(): UXImage
                {
                    return new UXImage('res://resources/expui/icons/fileTypes/image_dark.png');
                }
            },
            new class extends FileType
            {
                public function validate(File $file): bool
                {
                    return in_array(fs::ext($file), ['java']);
                }

                public function getIcon(): UXImage
                {
                    return new UXImage('res://resources/expui/icons/fileTypes/java_dark.png');
                }
            },
            new class extends FileType
            {
                public function validate(File $file): bool
                {
                    return in_array(fs::ext($file), ['json']);
                }

                public function getIcon(): UXImage
                {
                    return new UXImage('res://resources/expui/icons/fileTypes/json_dark.png');
                }
            },
            new class extends FileType
            {
                public function validate(File $file): bool
                {
                    return in_array(fs::ext($file), ['sh', 'bat']);
                }

                public function getIcon(): UXImage
                {
                    return new UXImage('res://resources/expui/icons/fileTypes/shell_dark.png');
                }
            },
            new class extends FileType
            {
                public function validate(File $file): bool
                {
                    return in_array(fs::ext($file), ['db', 'sqlite']);
                }

                public function getIcon(): UXImage
                {
                    return new UXImage('res://resources/expui/icons/fileTypes/sql_dark.png');
                }
            },
            new class extends FileType
            {
                public function validate(File $file): bool
                {
                    return in_array(fs::ext($file), ['php']);
                }

                public function getIcon(): UXImage
                {
                    return new UXImage('res://resources/expui/icons/class_dark.png');
                }
            },
            new class extends FileType
            {
                public function validate(File $file): bool
                {
                    return in_array(fs::ext($file), ['yml', 'yaml']);
                }

                public function getIcon(): UXImage
                {
                    return new UXImage('res://resources/expui/icons/fileTypes/yaml_dark.png');
                }
            },


        ];
    }
};