<?php
namespace ide\ui;

use action\Animation;
use ide\forms\MainForm;
use ide\Ide;
use ide\systems\FxeBottomConsoleSystem;
use ide\systems\LoadingManager;
use php\gui\event\UXMouseEvent;
use php\gui\framework\Preloader;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXButton;
use php\gui\UXHyperlink;
use php\gui\UXLabel;
use php\gui\UXNode;
use php\gui\UXPopupWindow;
use php\lib\str;

/**
 * Toast-уведомления FXE Studio (правый нижний угол главного окна).
 */
class FxeToast
{
    const TYPE_INFO = 'info';
    const TYPE_SUCCESS = 'success';
    const TYPE_WARNING = 'warning';
    const TYPE_ERROR = 'error';

    /** @var UXPopupWindow|null */
    public static $popup;

    /** @var UXVBox|null */
    public static $stack;

    /** @var array */
    public static $handles = [];

    /** @var bool */
    protected static $boundsListenerAttached = false;

    /** @var array */
    protected static $pending = [];

    /**
     * @param string $title
     * @param string $message
     * @param array $options type, timeout, actionText, actionHandler, linkText, linkHandler
     * @return FxeToastHandle
     */
    public static function show($title, $message = '', array $options = [])
    {
        $handle = new FxeToastHandle();

        self::enqueueShow($title, $message, $options, $handle);

        return $handle;
    }

    /**
     * @param string $title
     * @param string $message
     * @param array $options
     * @param FxeToastHandle $handle
     */
    protected static function enqueueShow($title, $message, array $options, FxeToastHandle $handle)
    {
        static::$pending[] = [
            'title' => $title,
            'message' => $message,
            'options' => $options,
            'handle' => $handle,
        ];

        uiLater(function () {
            FxeToast::flushPending();
        });
    }

    public static function flushPending()
    {
        if (!static::$pending) {
            return;
        }

        if (self::isBlockedByOverlay()) {
            waitAsync(250, function () {
                FxeToast::flushPending();
            });

            return;
        }

        $batch = static::$pending;
        static::$pending = [];

        foreach ($batch as $item) {
            self::displayToast(
                $item['title'],
                $item['message'],
                $item['options'],
                $item['handle']
            );
        }
    }

    /**
     * @deprecated use flushPending()
     */
    public static function flushPendingPublic()
    {
        self::flushPending();
    }

    /**
     * @return bool
     */
    public static function isBlockedByOverlay()
    {
        /** @var MainForm|null $form */
        $form = Ide::isCreated() ? Ide::get()->getMainForm() : null;

        if (!$form) {
            return true;
        }

        if (LoadingManager::isActive($form)) {
            return true;
        }

        if ($form->layout) {
            $preloader = Preloader::getPreloader($form->layout);

            if ($preloader && $preloader->visible) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $title
     * @param string $message
     * @param array $options
     * @param FxeToastHandle $handle
     */
    protected static function displayToast($title, $message, array $options, FxeToastHandle $handle)
    {
        /** @var MainForm|null $form */
        $form = Ide::get()->getMainForm();

        if (!$form) {
            static::$pending[] = [
                'title' => $title,
                'message' => $message,
                'options' => $options,
                'handle' => $handle,
            ];

            waitAsync(300, function () {
                FxeToast::flushPending();
            });

            return;
        }

        self::ensureHost();
        $toast = self::buildToast($title, $message, $options, $handle);
        self::$handles[spl_object_hash($handle)] = ['handle' => $handle, 'node' => $toast];
        self::$stack->children->add($toast);

        if (self::$popup->showing) {
            self::refreshHostBounds();
        } else {
            self::openHost();
        }

        $toast->opacity = 1;
        Animation::fadeIn($toast, 180);
        self::scheduleDismiss($handle, $toast, $options);
    }

    /**
     * @param string $text
     * @param int $timeout ms, 0 = auto
     * @return FxeToastHandle
     */
    public static function message($text, $timeout = 0)
    {
        if ($timeout <= 0) {
            $length = str::length(str::replace($text, ' ', ''));
            $timeout = max(2000, $length * 80);
        }

        return self::show('', $text, [
            'type' => self::TYPE_SUCCESS,
            'timeout' => $timeout,
        ]);
    }

    public static function dismissHandle(FxeToastHandle $handle)
    {
        uiLater(function () use ($handle) {
            $key = spl_object_hash($handle);
            $entry = self::$handles[$key] ?? null;

            if (!$entry) {
                return;
            }

            self::removeToast($entry['node'], $handle);
            unset(self::$handles[$key]);
        });
    }

    public static function ensureHost()
    {
        if (self::$popup) {
            return;
        }

        self::$stack = new UXVBox();
        self::$stack->spacing = 10;
        self::$stack->alignment = 'BOTTOM_RIGHT';
        self::$stack->fillWidth = true;
        self::$stack->maxWidth = 400;

        self::$popup = new UXPopupWindow();
        self::$popup->classes->add('fxe-toast-host');
        self::$popup->autoHide = false;
        self::$popup->autoFix = false;
        self::$popup->layout = self::$stack;

        self::attachBoundsListener();
    }

    protected static function attachBoundsListener()
    {
        if (self::$boundsListenerAttached) {
            return;
        }

        self::$boundsListenerAttached = true;

        uiLater(function () {
            /** @var MainForm|null $form */
            $form = Ide::get()->getMainForm();

            if (!$form) {
                self::$boundsListenerAttached = false;

                return;
            }

            $refresh = function () {
                if (self::$popup && self::$popup->showing && self::$stack && self::$stack->children->count() > 0) {
                    self::refreshHostBounds();
                }
            };

            foreach (['width', 'height', 'x', 'y', 'maximized', 'iconified'] as $prop) {
                try {
                    $form->observer($prop)->addListener(function () use ($refresh) {
                        $refresh();
                    });
                } catch (\Throwable $ignore) {
                }
            }

            if (isset($form->contentSplit)) {
                try {
                    $form->contentSplit->observer('height')->addListener(function () use ($refresh) {
                        $refresh();
                    });
                } catch (\Throwable $ignore) {
                }
            }
        });
    }

    public static function openHost()
    {
        /** @var MainForm|null $form */
        $form = Ide::get()->getMainForm();

        if (!$form) {
            return;
        }

        $bounds = self::calcHostBounds($form);

        self::$popup->width = $bounds['width'];
        self::$popup->height = $bounds['height'];
        self::$popup->show($form, $bounds['x'], $bounds['y']);
    }

    public static function refreshHostBounds()
    {
        if (!self::$popup || !self::$stack) {
            return;
        }

        /** @var MainForm|null $form */
        $form = Ide::get()->getMainForm();

        if (!$form) {
            return;
        }

        $bounds = self::calcHostBounds($form);

        self::$popup->width = $bounds['width'];
        self::$popup->height = $bounds['height'];

        if (self::$popup->showing) {
            self::$popup->show($form, $bounds['x'], $bounds['y']);
        }
    }

    public static function calcHostBounds(MainForm $form)
    {
        $margin = 16;
        $hostWidth = 400;
        $count = self::$stack ? self::$stack->children->count() : 0;
        $height = max(72, $count * 92 + ($count > 1 ? ($count - 1) * 10 : 0));

        $windowX = (float) $form->x;
        $windowY = (float) $form->y;
        $windowW = (float) $form->width;
        $windowH = (float) $form->height;

        if (isset($form->screenX) && (float) $form->screenX > 0) {
            $windowX = (float) $form->screenX;
        }

        if (isset($form->screenY) && (float) $form->screenY > 0) {
            $windowY = (float) $form->screenY;
        }

        if ($windowW <= 0) {
            $windowW = MainForm::MIN_WIDTH;
        }

        if ($windowH <= 0) {
            $windowH = MainForm::MIN_HEIGHT;
        }

        $bottomInset = 54 + 8;

        if ($form->statusPane && $form->statusPane->visible) {
            $bottomInset = max($bottomInset, (float) $form->statusPane->height + 8);
        }

        if (FxeBottomConsoleSystem::isVisible()) {
            $bottomInset += (int) Ide::get()->getUserConfigValue('ide.consoleHeight', 240) + 8;
        }

        if ($form->titleBarRow && $form->titleBarRow->visible) {
            $bottomInset += 0;
        }

        $x = $windowX + $windowW - $hostWidth - $margin;
        $y = $windowY + $windowH - $height - $margin - $bottomInset;

        $minY = $windowY + 48;
        $maxY = $windowY + $windowH - $height - $margin;

        if ($y < $minY) {
            $y = $minY;
        }

        if ($y > $maxY) {
            $y = max($minY, $maxY);
        }

        $minX = $windowX + $margin;
        $maxX = $windowX + $windowW - $hostWidth - $margin;

        if ($x < $minX) {
            $x = $minX;
        }

        if ($x > $maxX) {
            $x = max($minX, $maxX);
        }

        return [
            'x' => $x,
            'y' => $y,
            'width' => $hostWidth,
            'height' => $height,
        ];
    }

    public static function buildToast($title, $message, array $options, FxeToastHandle $handle)
    {
        $type = isset($options['type']) ? $options['type'] : self::TYPE_INFO;

        $root = new UXHBox();
        $root->spacing = 12;
        $root->padding = [14, 16, 14, 16];
        $root->alignment = 'TOP_LEFT';
        $root->classes->addAll(['fxe-toast', 'fxe-toast-' . $type]);
        $root->maxWidth = 400;
        $root->opacity = 1;

        $iconWrap = new UXLabel(self::iconGlyph($type));
        $iconWrap->classes->addAll(['fxe-toast-icon', 'fxe-toast-icon-' . $type]);
        $iconWrap->alignment = 'CENTER';
        $iconWrap->minWidth = 28;
        $iconWrap->prefWidth = 28;
        $iconWrap->minHeight = 28;
        $iconWrap->prefHeight = 28;

        $content = new UXVBox();
        $content->spacing = 6;
        UXHBox::setHgrow($content, 'ALWAYS');

        if ($title) {
            $titleLabel = new UXLabel($title);
            $titleLabel->classes->add('fxe-toast-title');
            $titleLabel->wrapText = true;
            $titleLabel->maxWidth = 320;
            $content->children->add($titleLabel);
        }

        if ($message) {
            $messageLabel = new UXLabel($message);
            $messageLabel->classes->add($title ? 'fxe-toast-message' : 'fxe-toast-title-solo');
            $messageLabel->wrapText = true;
            $messageLabel->maxWidth = 320;
            $content->children->add($messageLabel);
        }

        $actionText = isset($options['actionText']) ? $options['actionText'] : null;
        $linkText = isset($options['linkText']) ? $options['linkText'] : null;

        if ($actionText || $linkText) {
            $actions = new UXHBox();
            $actions->spacing = 12;
            $actions->alignment = 'CENTER_LEFT';
            $actions->classes->add('fxe-toast-actions');

            if ($actionText) {
                $button = new UXButton($actionText);
                $button->classes->add('fxe-toast-action-btn');

                if (!empty($options['actionHandler']) && is_callable($options['actionHandler'])) {
                    $button->on('action', function () use ($options, $handle, $root) {
                        call_user_func($options['actionHandler']);
                        self::removeToast($root, $handle);
                    });
                }

                $actions->children->add($button);
            }

            if ($linkText) {
                $link = new UXHyperlink($linkText);

                if (!empty($options['linkHandler']) && is_callable($options['linkHandler'])) {
                    $link->on('action', function () use ($options, $handle, $root) {
                        call_user_func($options['linkHandler']);
                        self::removeToast($root, $handle);
                    });
                }

                $actions->children->add($link);
            }

            $content->children->add($actions);
        }

        $root->children->addAll([$iconWrap, $content]);

        $root->on('click', function (UXMouseEvent $e) use ($handle, $root) {
            $target = $e->target;

            if ($target === null) {
                return;
            }

            if ($target === $root || FxeToast::isToastBackground($target, $root)) {
                $handle->triggerClick();
            }
        });

        return $root;
    }

    public static function isToastBackground($target, UXNode $root)
    {
        if ($target === null || $target === $root) {
            return $target === $root;
        }

        if ($target instanceof UXLabel) {
            $classes = (array) $target->classes;

            foreach (['fxe-toast-title', 'fxe-toast-message', 'fxe-toast-title-solo'] as $class) {
                if (in_array($class, $classes, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected static function iconGlyph($type)
    {
        switch ($type) {
            case self::TYPE_ERROR:
                return '!';
            case self::TYPE_WARNING:
                return '!';
            case self::TYPE_SUCCESS:
                return '✓';
            default:
                return 'i';
        }
    }

    public static function scheduleDismiss(FxeToastHandle $handle, UXNode $toast, array $options)
    {
        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 8000;

        if ($timeout <= 0) {
            return;
        }

        waitAsync($timeout, function () use ($handle, $toast) {
            uiLater(function () use ($handle, $toast) {
                self::removeToast($toast, $handle);
            });
        });
    }

    public static function removeToast(UXNode $toast, FxeToastHandle $handle)
    {
        if (!self::$stack || !$toast->parent) {
            $handle->triggerHide();

            return;
        }

        Animation::fadeOut($toast, 180, function () use ($toast, $handle) {
            if (self::$stack && $toast->parent) {
                self::$stack->children->remove($toast);
            }

            unset(self::$handles[spl_object_hash($handle)]);
            $handle->triggerHide();

            if (self::$stack && self::$stack->children->count() === 0 && self::$popup) {
                self::$popup->hide();
            } else {
                self::refreshHostBounds();
            }
        });
    }

    public static function mapLegacyType($type)
    {
        switch (str::upper($type)) {
            case 'ERROR':
                return self::TYPE_ERROR;
            case 'WARNING':
                return self::TYPE_WARNING;
            case 'SUCCESS':
                return self::TYPE_SUCCESS;
            case 'INFORMATION':
            case 'NOTICE':
            default:
                return self::TYPE_INFO;
        }
    }
}
