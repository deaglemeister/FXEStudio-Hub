<?php
namespace ide\editors\form;

use ide\editors\FormEditor;
use ide\formats\form\AbstractFormElement;
use php\gui\designer\UXDesignProperties;
use php\gui\UXNode;

/**
 * Построение панели свойств для массового редактирования компонентов в дизайнере.
 */
class BulkDesignHelper
{
    protected static $propertyOrder = [
        'visible', 'enabled', 'text', 'font', 'textColor',
        'fillColor', 'strokeColor', 'strokeWidth', 'smooth',
        'style', 'classesString', 'opacity', 'rotate',
        'position', 'size', 'tooltipText', 'cursor',
        'underline', 'wrapText', 'textAlignment', 'alignment',
        'focusTraversable', 'prefWidth', 'prefHeight',
    ];

    protected static function getPropertyOrder()
    {
        return static::$propertyOrder;
    }

    public static function buildProperties(FormEditor $formEditor, array $nodes)
    {
        $available = static::collectAvailableProperties($formEditor, $nodes);

        if (!$available) {
            return null;
        }

        $properties = new UXDesignProperties();
        $groupsAdded = [];
        $count = \sizeof($nodes);

        $groupTitles = [
            'general' => 'Главное',
            'extra' => 'Дополнительно',
        ];

        foreach (static::getPropertyOrder() as $code) {
            if (!isset($available[$code])) {
                continue;
            }

            $meta = $available[$code];
            $group = $meta['group'] ?: 'general';

            if (!isset($groupsAdded[$group])) {
                $title = isset($groupTitles[$group]) ? $groupTitles[$group] : 'Свойства';

                if (\sizeof($groupsAdded) === 0) {
                    $title = "Выделено: $count — $title";
                }

                $properties->addGroup($group, $title);
                $groupsAdded[$group] = true;
            }

            $editor = $meta['editorFactory']();

            if ($editor) {
                $properties->addProperty($group, $code, $meta['name'], $editor);
            }
        }

        return $properties;
    }

    public static function collectAvailableProperties(FormEditor $formEditor, array $nodes)
    {
        $union = [];

        foreach ($nodes as $node) {
            if (!$node instanceof UXNode) {
                continue;
            }

            $element = $formEditor->getFormat()->getFormElement($node);

            if (!$element instanceof AbstractFormElement) {
                continue;
            }

            foreach ($element->getProperties() as $code => $property) {
                if (isset($union[$code])) {
                    continue;
                }

                if ($property['editor'] === 'none' || $property['editor'] === 'id') {
                    continue;
                }

                if ($code === 'anchorFlags') {
                    continue;
                }

                $union[$code] = $property;
            }
        }

        return $union;
    }

    public static function entrySupportsProperty(array $entry, $code)
    {
        if (!$entry['element'] instanceof AbstractFormElement) {
            return false;
        }

        return isset($entry['element']->getProperties()[$code]);
    }
}
