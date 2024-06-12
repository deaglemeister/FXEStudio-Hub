<? 

namespace ide\forms\malboro;

use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXButton;
use php\gui\effect\UXGaussianBlurEffect;
use php\gui\UXLabel;
use action\Animation;
use php\gui\layout\UXScrollPane;


class Modals
{
    public $flag = false;
    private $current_modal = null;
    private $current_content = null;

    /* Функция модального диалога */
    function modal_dialog($form, $param, callable $callback = null)
    {
        if ($this->flag) {
            return;
        }
        $this->flag = true;

        # Создаем контейнер для открытия и просмотра поста
        $fullscreen_modal = new UXScrollPane;
        $fullscreen_modal->focusTraversable = false;
        $fullscreen_modal->id = "fullscreen_modal_container";
        $fullscreen_modal->fitToWidth = $param['fitToWidth'];
        $fullscreen_modal->fitToHeight = $param['fitToHeight'];
        $fullscreen_modal->anchors = ['left' => 0, 'top' => 0, 'right' => 0, 'bottom' => 0];
        $fullscreen_modal->opacity = 0;
        $fullscreen_modal->classes->add('fullscreen-modal');

        # Если передан параметр прозрачности
        $opacity = $param['opacity_overlay'] ?? "40";

        # Если передан параметр цвета
        $color = $param['color_overlay'] ?? "#777777";
        $fullscreen_modal->style = "-fx-background: {$color}{$opacity}";

        # Передан ли контент для overlay
        if ($param['content']) {
            $dialog_container = $param['content'];

            # Параметр растягивать контент по высоте
            if ($param['contentFitToHeight']) {
                UXVBox::setVgrow($dialog_container, 'ALWAYS');
            }
        } else {
            # Контейнер диалога
            $dialog_container = new UXVBox;
            $dialog_container->classes->add('dialog-container');
            $dialog_container->on('click', function ($e) {
                $e->consume();
            });

            # Заголовок и сообщение
            $title = new UXLabel($param['title']);
            $title->classes->addAll(['title', 'font-bold']);
            $message = new UXLabel($param['message']);
            $message->classes->addAll(['message', 'font-gray']);

            # Контейнер кнопок
            $button_container = new UXHBox;
            $dialog_container->opacity = 0;
            $button_container->classes->add('button-container');

            # Добавляем кнопки в контейнер
            if (!empty($param['buttons'])) {
                foreach ($param['buttons'] as $b) {
                    $button = new UXButton;
                    $button->text = $b['text'];
                    $button->classes->add($b['style']);
                    $button->on('action', function () use ($callback, $form, $param, $fullscreen_modal, $dialog_container, $b) {
                        if ($b['close']) {
                            $close_modal = ['modal' => $fullscreen_modal, 'content' => $dialog_container, 'blur' => $param['blur']];
                            $this->modal_close($close_modal);
                        } else {
                            $callback($b['text']);
                            $close_modal = ['modal' => $fullscreen_modal, 'content' => $dialog_container, 'blur' => $param['blur']];
                            $this->modal_close($close_modal);
                        }
                    });
                    $button_container->add($button);
                }
            }

            # Собираем диалог
            $dialog_container->add($title);
            $dialog_container->add($message);
            if (!empty($param['buttons'])) {
                $dialog_container->add($button_container);
            }
        }

        # Добавляем действия для модального окна
        $fullscreen_modal->on('keyUp', function ($e) use ($param, $fullscreen_modal, $dialog_container) {
            if ($e->codeName == 'Esc') {
                $close_modal = ['modal' => $fullscreen_modal, 'content' => $dialog_container, 'blur' => $param['blur']];
                $this->modal_close($close_modal);
            }
        });
        $fullscreen_modal->on('click', function ($e) use ($param, $fullscreen_modal, $dialog_container) {
            if ($e->button == 'PRIMARY') {
                if ($param['close_overlay']) {
                    $close_modal = ['modal' => $fullscreen_modal, 'content' => $dialog_container, 'blur' => $param['blur']];
                    $this->modal_close($close_modal);
                }
            }
        });

        # Контент скролл контейнера
        $fullscreen_modal_content = new UXVbox([$dialog_container]);
        $fullscreen_modal_content->anchors = ['left' => 0, 'top' => 0, 'right' => 0, 'bottom' => 0];
        $fullscreen_modal_content->classes->add('content');

        # Если передан параметр прозрачности
        $fullscreen_modal_content->padding = $param['padding'] ?? [60, 60, 60, 60];

        $fullscreen_modal->content = $fullscreen_modal_content;
        $form->add($fullscreen_modal);

        # Сохраняем текущие модальные элементы
        $this->current_modal = $fullscreen_modal;
        $this->current_content = $dialog_container;

        # Открываем скроллпан на весь экран
        Animation::fadeIn($fullscreen_modal, 130, function () use ($fullscreen_modal, $dialog_container, $param, $callback) {
            $fullscreen_modal->requestFocus();
            if ($param['blur'] && $param['blur']->effects->count == 0) {
                $param['blur']->effects->add(new UXGaussianBlurEffect(3));
            }
            Animation::fadeIn($dialog_container, 130);
            if ($callback) {
                $param_open = [
                    'open' => true,
                    'modal' => $fullscreen_modal,
                    'content' => $dialog_container,
                    'blur' => $param['blur']
                ];
                $callback($param_open);
            }
        });
    }

    /* Функция закрытия модального диалога */
    function modal_close($param)
    {
        $this->flag = false;
        Animation::fadeOut($param['content'], 130, function () use ($param) {
            if ($param['blur']) {
                $param['blur']->effects->clear();
            }
            Animation::fadeOut($param['modal'], 130, function () use ($param) {
                $param['modal']->free();
            });
        });

        # Сбрасываем текущие модальные элементы
        $this->current_modal = null;
        $this->current_content = null;
    }

    function close_current_modal()
    {
        if ($this->current_modal && $this->current_content) {
            $this->modal_close(['modal' => $this->current_modal, 'content' => $this->current_content, 'blur' => $this->current_content->getForm()->flowPane]);
        }
    }
}
