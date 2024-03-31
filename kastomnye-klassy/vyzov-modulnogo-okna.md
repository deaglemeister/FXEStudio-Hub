# Вызов модульного окна

## Объявление о классе

Создаём в конструкторе компонент модуль

{% tabs %}
{% tab title="Модуль modal" %}
```php
# Создаем название модуля modal

    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция модального диалога
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    
    /* Функция модального диалога */
    function modal_dialog(AbstractForm $form, $param, callable $callback = null) {
        
        # Создаем контейнер для открытия и просмотра поста
        $fullscreen_modal = new UXScrollPane;
        $fullscreen_modal->focusTraversable = false;
        $fullscreen_modal->id = "fullscreen_modal_container";
        $fullscreen_modal->fitToWidth = $param['fitToWidth'];
        $fullscreen_modal->fitToHeight =  $param['fitToHeight'];
        $fullscreen_modal->anchors = ['left' => 0, 'top' => 0, 'right' => 0, 'bottom' => 0];
        $fullscreen_modal->opacity = 0;
        $fullscreen_modal->classes->add('fullscreen-modal');
        
        # Если передан параметр прозрачности
        if ($param['opacity_overlay']) {
            $opacity = $param['opacity_overlay'];
        } else {
            $opacity = "40";
        }
        
        # Если передан параметр цвета
        if ($param['color_overlay']) {
            $fullscreen_modal->style = "-fx-background: ".$param['color_overlay'].$opacity;
        } else {
            $fullscreen_modal->style = "-fx-background: #777777".$opacity;
        }
        
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
            $dialog_container->on('click', function($e) {
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
            
            # Собираем диалог
            $dialog_container->add($title);
            $dialog_container->add($message);
            $dialog_container->add($button_container);
            
        }
        
        # Добавляем действия для модального окна
        $fullscreen_modal->on('keyUp', function($e) use ($param, $fullscreen_modal, $dialog_container) {
            if ($e->codeName == 'Esc') {
                $close_modal = ['modal' => $fullscreen_modal, 'content' => $dialog_container, 'blur' => $param['blur']];
                app()->module("modal")->modal_close($close_modal);
            }
        });
        $fullscreen_modal->on('click', function($e) use ($form, $param, $fullscreen_modal, $dialog_container) {
            if ($e->button == 'PRIMARY') {
                if ($param['close_overlay']) {
                    $close_modal = ['modal' => $fullscreen_modal, 'content' => $dialog_container, 'blur' => $param['blur']];
                    app()->module("modal")->modal_close($close_modal);
                }
            }
        });
        
        # Контент скролл контейнера
        $fullscreen_modal_content = new UXVbox([$dialog_container]);
        $fullscreen_modal_content->anchors = ['left' => 0, 'top' => 0, 'right' => 0, 'bottom' => 0];
        $fullscreen_modal_content->classes->add('content');
        
        # Если передан параметр прозрачности
        if ($param['padding']) {
            $fullscreen_modal_content->padding = $param['padding'];
        } else {
            $fullscreen_modal_content->padding = [60,60,60,60];
        }
        
        $fullscreen_modal->content = $fullscreen_modal_content;
        $form->add($fullscreen_modal);
        
        # Открываем скроллпан на весь экран
        Animation::fadeIn($fullscreen_modal, 130, function () use ($fullscreen_modal, $dialog_container, $param, $callback) {
            $fullscreen_modal->requestFocus();
            if ($param['blur']) {
                if ($param['blur']->effects->count == 0) {
                    $param['blur']->effects->add(new UXGaussianBlurEffect(3));
                }
            }
            Animation::fadeIn($dialog_container, 130);
            if (!empty($callback)) {
                $param_open = ['open' => true,
                               'modal' => $fullscreen_modal,
                               'content' => $dialog_container,
                               'blur' => $param['blur']
                               ];
                $callback($param_open);
            }
        });
    }
    
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция закрытия модального диалога
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    
    /* Функция закрытия модального диалога */
    function modal_close($param) {
    
        Animation::fadeOut($param['content'], 130, function() use ($param) {
            if ($param['blur']) {
                $param['blur']->effects->clear();
            }
            Animation::fadeOut($param['modal'], 130, function() use ($param) {
                $param['modal']->free();
            });
        });
    
    }
```
{% endtab %}

{% tab title="Вызов модульного окна" %}


```php
# Добавляем объект на форму и назначаем ему событие и вставляем в него код
    
# Параметры модального окна
    $modal = [
        'fitToWidth' => true, # Во всю длину
        'fitToHeight' => true, # Во всю ширину
        'blur' => $this->flowPane, # Объект для размытия
        'title' => 'Заголовок сообщения', # Заголовок окна
        'message' => 'Текст Вашего сообщения', # Сообщение
        'close_overlay' => true, # Закрывать при клике мыши на overlay
        'buttons' => [['text' => 'Да', 'style' => 'button-red'], ['text' => 'Отмена', 'style' => 'button-accent', 'close' => true]]
        ];
    # Отображаем окно      
    app()->module("modal")->modal_dialog($this, $modal, function($e) use ($this) {
        # Если выбран ответ
        if ($e == 'Да') {
            alert('Вы выбрали '.$e);
        }
    });
```
{% endtab %}

{% tab title="CSS Modal" %}
```css
.root, .FormEditor {
    -fx-accent: #41a0da;
    -fx-green: #2da55d;
    -fx-blue: #429fd8;
    -fx-red: #cc2600;
    -fx-yellow: #ce8b52;
    -fx-gray: #ce8b52;
}



/* message запись */
.message-item {
    -fx-background-color: rgba(255,255,255, 0.9);
    -fx-background-radius: 3;
    -fx-border-radius: 3;
    -fx-border-width: 0 0 1 0;
    -fx-border-color: transparent transparent -fx-hover-base transparent;
    -fx-padding: 10 10 10 10;
    -fx-max-width: 400;
    -fx-effect: dropshadow(two-pass-box , rgba(0, 0, 0, 0.2), 10, 0.0 , 0, 2);
}

.message-item .message {
    -fx-padding: 0 0 5 0;
    -fx-text-fill: derive(-fx-mid-text-color, 20%);
}



/* Дополнительные вспомогающие классы */
/* ---------------------------------------------------------------------------------------*/
.font-regular {
    -fx-font-family: 'Arial', san-serif !Important;
}
.font-bold {
    -fx-font-weight: 700;
}
.font-light {
}

.font-h1 {
    -fx-font-size: 2.038em !Important;
}
.font-h2 {
    -fx-font-size: 1.338em !Important;
}
.font-h3 {
    -fx-font-size: 1.125em !Important;
}
.font-h4 {
    -fx-font-size: 0.8em !Important;
}
.font-accent {
    -fx-text-fill: -fx-accent !Important;
}
.font-green {
    -fx-text-fill: -fx-green !Important;
}
.font-red {
    -fx-text-fill: -fx-red !Important;
}
.font-blue {
    -fx-text-fill: -fx-blue !Important;
}
.font-yellow {
    -fx-text-fill: -fx-yellow !Important;
}
.font-black {
    -fx-text-fill: -fx-text-base-color !Important;
}
.font-gray {
    -fx-text-fill: derive(-fx-text-base-color, 70%) !Important;
}
.font-white-gray {
    -fx-text-fill: derive(-fx-text-base-color, 110%) !Important;
}
.font-white {
    -fx-text-fill: #fff !Important;
}



/* Цветные кнопки */
/* ---------------------------------------------------------------------------------------*/
.button-accent {
    -fx-base: -fx-accent;
    -fx-text-fill: #fff !Important;
}
.button-red {
    -fx-base: -fx-red;
    -fx-text-fill: #fff !Important;
}
.button-blue {
    -fx-base: -fx-blue;
    -fx-text-fill: #fff !Important;
}
.button-green {
    -fx-base: -fx-green;
    -fx-text-fill: #fff !Important;
}
.button-yellow {
    -fx-base: -fx-yellow;
    -fx-text-fill: #fff !Important;
}






/* Контейнер модального окна */
.fullscreen-modal {
    -fx-alignment: center;
}
.fullscreen-modal .content {
    -fx-alignment: center;
}
.fullscreen-modal .content .anchor {
    -fx-alignment: center;
    -fx-padding: 10;

}
.fullscreen-modal .content .dialog-container {
    -fx-alignment: center;
    -fx-max-width: 350;
    -fx-padding: 15;
    -fx-spacing: 15;
    -fx-border-radius: 5;
    -fx-background-color: rgba(255, 255, 255, 0.95);
    -fx-background-radius: 5;
    -fx-effect: dropshadow(two-pass-box , rgba(0, 0, 0, 0.5), 10, 0.0 , 0, 0);
}

/* Контейнер диалога на главной форме */
.fullscreen-modal .content .title {

}
.fullscreen-modal .content .message {

}
.fullscreen-modal .content .title {
    
}
.fullscreen-modal .content .button-container {
    -fx-alignment: center;
    -fx-spacing: 10;
}
.fullscreen-modal .content .button-container .button {
    -fx-min-width: 75;
    -fx-min-height: 32;
}

/* Контейнер просмотра превью на главной форме */
.fullscreen-modal .content .image {
    -fx-effect: dropshadow(two-pass-box , rgba(0, 0, 0, 0.3), 10, 0.0 , 0, 0);
}
.fullscreen-modal .content .box-param {
    -fx-alignment: center;
    -fx-spacing: 10;
    -fx-effect: dropshadow(two-pass-box , rgba(0, 0, 0, 0.5), 10, 0.0 , 0, 0);
    -fx-padding: 5 10 5 10;
    -fx-cursor: hand;
}



/* Стили Кропа изображений */
.fullscreen-modal .crop-content {
    -fx-alignment: CENTER;
}
.fullscreen-modal .crop-content .image-box {
    -fx-min-width: 200;
    -fx-min-height: 200;
    -fx-alignment: CENTER;
    -fx-background-color: rgba(255,255,255,0.9);
    -fx-padding: 0;
    -fx-spacing: 20;
    -fx-background-radius: 5;
    -fx-effect: dropshadow(two-pass-box , rgba(0, 0, 0, 0.2), 10, 0.0 , 0, 2);
}
.fullscreen-modal .crop-content .image-box .top-pane {
    -fx-padding: 15;
    -fx-alignment: CENTER;
    -fx-background-color: derive(-fx-background, 50%);
}
.fullscreen-modal .crop-content .image-box .inf-pane {
    -fx-alignment: CENTER;
}
.fullscreen-modal .crop-content .image-box .bottom-pane {
    -fx-padding: 15;
    -fx-spacing: 10;
    -fx-alignment: CENTER;
    -fx-background-color: derive(-fx-background, 50%);
}
.fullscreen-modal .crop-content .image-box .bottom-pane .button-save {
    -fx-min-width: 75;
    -fx-min-height: 32;
}
.fullscreen-modal .crop-content .image-box .bottom-pane .button-cancel {
    -fx-min-width: 75;
    -fx-min-height: 32;
}
.fullscreen-modal .crop-content .image-box .bottom-pane .button-exit {
    -fx-min-width: 75;
    -fx-min-height: 32;
}
.fullscreen-modal .crop-content .image-box .bottom-pane .button-next {
    -fx-min-width: 75;
    -fx-min-height: 32;
}
.fullscreen-modal .crop-content .image-box .image-anchor {
    -fx-min-width: 200;
    -fx-min-height: 200;
    -fx-padding: 0 20 0 20;
    -fx-alignment: CENTER;
}
.fullscreen-modal .crop-content .image-anchor .crop-pane {
    -fx-background-color: rgba(0,0,0, 0.3);
    -fx-border-color: rgba(0,0,0, 0.5);
    -fx-border-style: segments(0.166667em, 0.166667em);
    -fx-border-width: 2;
    -fx-background-radius: 3;
    -fx-border-radius: 3;
}




/* Левый контейнер меню и поиска */
/* ---------------------------------------------------------------------------------------*/
.left-menu-programm {
    -fx-padding: 0;
    -fx-spacing: 15;
    -fx-alignment: top_center;
    -fx-min-width: 230;
    -fx-effect: dropshadow(two-pass-box , rgba(0,0,0, 0.2), 10, 0 , 0, 0);
    -fx-background-radius: 0 5 5 0;
}
.left-menu-programm .content {
    -fx-alignment: top_center;
    -fx-background-color: -fx-hover-base;
    -fx-background-radius: 0 5 5 0;
}
.left-menu-programm .user-profile {
    -fx-spacing: 10;
    -fx-padding: 20;
    -fx-alignment: center;
    -fx-border-color: -fx-pressed-base;
    -fx-border-width: 0 0 1 0;
    -fx-cursor: hand;
}
.left-menu-programm .user-profile .name {
}
.left-menu-programm .links {
    -fx-spacing: 0;
    -fx-padding: 20;
    -fx-alignment: center_left;
    -fx-border-color: -fx-pressed-base;
    -fx-border-width: 0 0 1 0;
}
.left-menu-programm .links .link {
    -fx-cursor: hand;
    -fx-padding: 10 15 10 10;
}
.left-menu-programm .links .link:hover {
    -fx-background-color: -fx-pressed-base;
}
.left-menu-programm .inf {
    -fx-spacing: 0;
    -fx-padding: 20;
    -fx-alignment: center_left;
}





/*******************************************************************************
 *                                                                             *
 * ScrollBar                                                                   *
 *                                                                             *
 ******************************************************************************/

.scroll-bar:horizontal {
    -fx-background-color: -fx-background;
    -fx-padding: 0;
    -fx-background-insets: 0;
    -fx-focus-color: transparent;
    -fx-faint-focus-color: transparent;
}
.scroll-bar:vertical {
    -fx-background-color: -fx-background;
    -fx-padding: 0;
    -fx-background-insets: 0;
    -fx-focus-color: transparent;
    -fx-faint-focus-color: transparent;
}
.scroll-bar:focused {
    -fx-background-color: -fx-background;
    -fx-padding: 0;
    -fx-background-insets: 0;
    -fx-focus-color: transparent;
    -fx-faint-focus-color: transparent;
}
.scroll-bar:vertical:focused {
    -fx-background-color: -fx-background;
    -fx-padding: 0;
    -fx-background-insets: 0;
    -fx-focus-color: transparent;
    -fx-faint-focus-color: transparent;
}
.scroll-bar > .thumb {
    -fx-background-color: -fx-outer-border, -fx-body-color, -fx-body-color;
    /*-fx-background-insets: 1, 2, 3;*/
    -fx-background-insets: 2, 3, 4;
    /*-fx-background-radius: 0.416667em, 0.333333em, 0.25em; *//* 5, 4,3 */
    -fx-background-radius: 3, 2, 1;
}
.scroll-bar:vertical > .thumb {
    -fx-background-color: -fx-outer-border, -fx-body-color, -fx-body-color-to-right;
}
.scroll-bar > .increment-button,
.scroll-bar > .decrement-button {
    -fx-background-color: transparent, transparent, transparent;
    -fx-color: transparent;
    -fx-padding: 0.25em; /* 3px */
}
.scroll-bar:horizontal > .increment-button,
.scroll-bar:horizontal > .decrement-button {
    -fx-background-insets: 2 1 2 1, 3 2 3 2, 4 3 4 3;
}
.scroll-bar:vertical > .increment-button,
.scroll-bar:vertical > .decrement-button {
    -fx-background-insets: 1 2 1 2, 2 3 2 3, 3 4 3 4;
}
.scroll-bar > .increment-button > .increment-arrow,
.scroll-bar > .decrement-button > .decrement-arrow {
    -fx-background-color: -fx-mark-highlight-color,derive(-fx-base,-45%);
}
.scroll-bar > .increment-button:hover > .increment-arrow,
.scroll-bar > .decrement-button:hover > .decrement-arrow {
    -fx-background-color: -fx-mark-highlight-color, derive(-fx-base,-50%);
}
.scroll-bar > .increment-button:pressed > .increment-arrow,
.scroll-bar > .decrement-button:pressed > .decrement-arrow {
    -fx-background-color: -fx-mark-highlight-color, derive(-fx-base,-55%);
}
.scroll-bar:horizontal > .decrement-button > .decrement-arrow {
    -fx-padding: 0.333em 0.167em 0.333em 0.167em; /* 4 2 4 2 */
    -fx-shape: "M5.997,5.072L5.995,6.501l-2.998-4l2.998-4l0.002,1.43l-1.976,2.57L5.997,5.072z";
    -fx-effect: dropshadow(two-pass-box , -fx-shadow-highlight-color, 1, 0.0 , 0, 1.4);
    /*-fx-background-insets: 2 0 -2 0, 0;*/
}
.scroll-bar:horizontal > .increment-button > .increment-arrow {
    -fx-padding: 0.333em 0.167em 0.333em 0.167em; /* 4 2 4 2 */
    -fx-shape: "M2.998-0.07L3-1.499l2.998,4L3,6.501l-0.002-1.43l1.976-2.57L2.998-0.07z";
    -fx-effect: dropshadow(two-pass-box , -fx-shadow-highlight-color, 1, 0.0 , 0, 1.4);
    /*-fx-background-insets: 2 0 -2 0, 0;*/
}
.scroll-bar:vertical > .decrement-button > .decrement-arrow {
    -fx-padding: 0.167em 0.333em 0.167em 0.333em; /* 2 4 2 4 */
    -fx-shape: "M1.929,4L0.5,3.998L4.5,1l4,2.998L7.07,4L4.5,2.024L1.929,4z";
    -fx-effect: dropshadow(two-pass-box , -fx-shadow-highlight-color, 1, 0.0 , 0, 1.4);
    /*-fx-background-insets: 2 0 -2 0, 0;*/
}
.scroll-bar:vertical > .increment-button > .increment-arrow {
    -fx-padding: 0.167em 0.333em 0.167em 0.333em; /* 2 4 2 4 */
    -fx-shape: "M7.071,1L8.5,1.002L4.5,4l-4-2.998L1.93,1L4.5,2.976L7.071,1z";
    -fx-effect: dropshadow(two-pass-box , -fx-shadow-highlight-color, 1, 0.0 , 0, 1.4);
    /*-fx-background-insets: 2 0 -2 0, 0;*/
}


/*******************************************************************************
 *                                                                             *
 * ScrollPane                                                                  *
 *                                                                             *
 ******************************************************************************/
.scroll-pane {
    -fx-background-color: transparent;
    -fx-focus-color: transparent;
    -fx-faint-focus-color: transparent;
}
.scroll-pane:focused {
    -fx-background-color: transparent;
    -fx-focus-color: transparent;
    -fx-faint-focus-color: transparent;
}
.scroll-pane:focused > .viewport {
    -fx-background-color: -fx-background;
}
.scroll-pane > .viewport {
    -fx-background-color: -fx-background;
}
.scroll-pane > .scroll-bar:horizontal {
    -fx-background-insets: 0 0 0 0, 1;
    -fx-padding: 0 1 0 1;
}
.scroll-pane > .scroll-bar:horizontal > .increment-button,
.scroll-pane > .scroll-bar:horizontal > .decrement-button {
    -fx-padding: 0.166667em 0.25em 0.25em  0.25em; /* 2 3 3 3 */
}
.scroll-pane > .scroll-bar:vertical > .increment-button,
.scroll-pane > .scroll-bar:vertical > .decrement-button {
    -fx-padding: 0.25em 0.25em 0.25em 0.166667em; /* 3 3 3 2 */
}
.scroll-pane > .scroll-bar:vertical {
    -fx-background-insets: 0 0 0 0, 1;
    -fx-padding: 1 0 1 0;
}
.scroll-pane > .corner {
    -fx-background-color: derive(-fx-base,-1%);
    -fx-background-insets: 0 1 1 0;
}
/* new styleclass for edge to edge scrollpanes that don't want to draw a border */
.scroll-pane.edge-to-edge,
.tab-pane > * > .scroll-pane {
    -fx-background-color: -fx-background;
    -fx-background-insets: 0;
    -fx-padding: 0;
}
.scroll-pane.edge-to-edge > .scroll-bar,
.tab-pane > * > .scroll-pane > .scroll-bar,
.titled-pane > .content > .scroll-pane > .scroll-bar {
    -fx-background-insets: 0;
    -fx-padding: 0;
}
.scroll-pane.edge-to-edge > .scroll-bar > .increment-button,
.scroll-pane.edge-to-edge > .scroll-bar > .decrement-button,
.tab-pane > * > .scroll-pane > .scroll-bar > .increment-button,
.tab-pane > * > .scroll-pane > .scroll-bar > .decrement-button,
.titled-pane > .content > .scroll-pane > .scroll-bar > .increment-button,
.titled-pane > .content > .scroll-pane > .scroll-bar > .decrement-button {
    -fx-padding: 0.25em; /* 3px */
}
```
{% endtab %}
{% endtabs %}

{% hint style="info" %}
**Внимание:** Данный класс может быть обновлён, и в него могут быть добавлены новые свойства.
{% endhint %}

