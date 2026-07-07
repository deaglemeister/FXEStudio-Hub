<?php
namespace ide\ui;

use ide\Ide;
use ide\utils\UiUtils;
use php\gui\designer\FxeMainWindowChrome;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\text\UXFont;
use php\gui\UXButton;
use php\gui\UXForm;
use php\gui\UXLabel;
use php\gui\layout\UXScrollPane;
use php\gui\UXTextArea;

/**
 * Диалог ошибки FXE Studio: тёмный titlebar, Darcula-тело, stack trace.
 */
class FxeErrorDialog
{
    /**
     * @param \Throwable $e
     * @param array $options title, header, summary, exitLabel, continueLabel, detailsExpanded
     * @return string|null exit|continue|null
     */
    public static function show(\Throwable $e, array $options = [])
    {
        $title = isset($options['title']) ? $options['title'] : 'Ошибка';
        $header = isset($options['header']) ? $options['header'] : 'Произошла ошибка в FXE Studio';
        $summary = isset($options['summary']) ? $options['summary'] : $e->getMessage();
        $exitLabel = isset($options['exitLabel']) ? $options['exitLabel'] : 'Выход из FXE Studio';
        $continueLabel = isset($options['continueLabel']) ? $options['continueLabel'] : 'Продолжить';
        $detailsExpanded = !isset($options['detailsExpanded']) || $options['detailsExpanded'];

        $result = ['value' => null];

        $form = new UXForm();
        $form->owner = Ide::get()->getMainForm();
        $form->modality = 'APPLICATION_MODAL';
        $form->title = $title;
        $form->style = 'UTILITY';
        $form->resizable = true;
        $form->minWidth = 640;
        $form->minHeight = 380;
        $form->width = 760;
        $form->height = $detailsExpanded ? 520 : 260;

        static::applyTheme($form);

        $form->on('show', function () use ($form) {
            try {
                FxeMainWindowChrome::applyDialog($form);
            } catch (\Throwable $ignore) {
            }
        });

        $root = new UXVBox();
        $root->spacing = 0;
        $root->style = UiUtils::fontSizeStyle();
        $root->classes->addAll(['fxe-error-dialog', 'fxe-error-dialog-window']);

        $headerRow = new UXHBox();
        $headerRow->spacing = 14;
        $headerRow->alignment = 'TOP_LEFT';
        $headerRow->classes->add('fxe-error-dialog-header');

        $icon = new UXLabel('!');
        $icon->classes->add('fxe-error-dialog-icon');
        $icon->alignment = 'CENTER';

        $headerText = new UXVBox();
        $headerText->spacing = 6;
        UXHBox::setHgrow($headerText, 'ALWAYS');

        $headerLabel = new UXLabel($header);
        $headerLabel->wrapText = true;
        $headerLabel->classes->add('fxe-error-dialog-heading');

        $summaryLabel = new UXLabel($summary);
        $summaryLabel->wrapText = true;
        $summaryLabel->classes->add('fxe-error-dialog-summary');

        $headerText->children->addAll([$headerLabel, $summaryLabel]);
        $headerRow->children->addAll([$icon, $headerText]);

        $detailsArea = new UXTextArea(static::formatDetails($e));
        $detailsArea->editable = false;
        $detailsArea->wrapText = false;
        $detailsArea->font = new UXFont(12, 'Consolas');
        $detailsArea->classes->add('fxe-error-dialog-details-text');

        $detailsScroll = new UXScrollPane($detailsArea);
        $detailsScroll->fitToWidth = true;
        $detailsScroll->classes->add('fxe-error-dialog-details');
        UXVBox::setVgrow($detailsScroll, 'ALWAYS');

        $footer = new UXHBox();
        $footer->spacing = 10;
        $footer->alignment = 'CENTER_LEFT';
        $footer->classes->add('fxe-error-dialog-footer');

        $toggleBtn = new UXButton($detailsExpanded ? 'Скрыть детали' : 'Показать детали');
        $toggleBtn->classes->add('fxe-error-dialog-btn-ghost');

        $spacer = new UXHBox();
        UXHBox::setHgrow($spacer, 'ALWAYS');

        $exitBtn = new UXButton($exitLabel);
        $exitBtn->classes->add('fxe-error-dialog-btn-danger');

        $continueBtn = new UXButton($continueLabel);
        $continueBtn->classes->add('fxe-error-dialog-btn-primary');
        $continueBtn->defaultButton = true;

        $footer->children->addAll([$toggleBtn, $spacer, $exitBtn, $continueBtn]);

        $root->children->addAll([$headerRow, $detailsScroll, $footer]);
        $form->layout = $root;

        $state = ['expanded' => $detailsExpanded];

        $toggleBtn->on('action', function () use ($toggleBtn, $detailsScroll, $form, $state) {
            $state['expanded'] = !$state['expanded'];
            $detailsScroll->visible = $state['expanded'];
            $detailsScroll->managed = $state['expanded'];
            $toggleBtn->text = $state['expanded'] ? 'Скрыть детали' : 'Показать детали';
            $form->height = $state['expanded'] ? 520 : 260;
            $form->centerOnScreen();
        });

        $detailsScroll->visible = $detailsExpanded;
        $detailsScroll->managed = $detailsExpanded;

        $exitBtn->on('action', function () use ($form, &$result) {
            $result['value'] = 'exit';
            $form->hide();
        });

        $continueBtn->on('action', function () use ($form, &$result) {
            $result['value'] = 'continue';
            $form->hide();
        });

        $form->on('hide', function () use (&$result) {
            if ($result['value'] === null) {
                $result['value'] = 'continue';
            }
        });

        $form->centerOnScreen();
        $form->showAndWait();

        return $result['value'];
    }

    /**
     * @param UXForm $form
     */
    protected static function applyTheme(UXForm $form)
    {
        $form->addStylesheet('/php/gui/framework/style.css');

        if (function_exists('app')) {
            foreach (app()->getStyles() as $stylesheet) {
                $form->addStylesheet($stylesheet);
            }
        }

        // UXForm::style — StageStyle (UTILITY/DECORATED), не inline CSS.
    }

    /**
     * @param \Throwable $e
     * @return string
     */
    protected static function formatDetails(\Throwable $e)
    {
        $class = get_class($e);

        return "{$class}\n{$e->getMessage()}\n\n"
            . "Файл: {$e->getFile()}\n"
            . "Строка: {$e->getLine()}\n\n"
            . $e->getTraceAsString();
    }
}
