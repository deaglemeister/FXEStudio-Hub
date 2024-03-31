# Кроп изоброжение

## Объявление о классе

Создаём в конструкторе IDE любой объект, и добавляем ему любое событие.

{% tabs %}
{% tab title="Модуль crop" %}
```php
# Создаем название модуля crop
# CSS Code нужно использовать из вызова модульного окна

class crop extends AbstractModule
{
    private $_match;
    /** @var UXAnchorPane **/
    private $_anchor;
    /** @var UXImageArea **/
    private $_img;
    /** @var UXPane **/
    private $_crop_pane;
    private $_crop_start;
    
    private $_dragging = true;
    private $_proportional = true;
    private $_proportional_aspect = 1; // width : height
    private $_min_size = [50, 50];
    
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция получения обрезаного изображения
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    function get_cropped_image() {
    
        # Получаем объекты
        list($match, $img) = [$this->_match, $this->_img];
    
        # Проверяем выделеную область
        if ($this->check_rect_valid()) {
            return $this->crop($img->image, $match['x'], $match['y'], $match['w'], $match['h']);
        }
        return false;
        
    }
    
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция проверяет валидность выделеной области
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    function check_rect_valid() {
        
        # Получаем объекты
        list($crop_pane) = [$this->_crop_pane];
    
        # Высчитываем параметры
        $match = $this->_match = $this->crop_match_parameters();
        
        # Вычисляем максимально возможно положение элементов
        $max_x_position = $match['space_x'] + $match['img_new_width'];
        $max_y_position = $match['space_y'] + $match['img_new_height'];
        
        $position = ($crop_pane->position[0] < $match['space_x'] or $crop_pane->position[1] < $match['space_y'] or $crop_pane->position[0] + $crop_pane->size[0] > $max_x_position or $crop_pane->position[1] + $crop_pane->size[1] > $max_y_position); 
        $size = ($match['w'] >= $this->_min_size[0] and $match['h'] >= $this->_min_size[1] and ($this->_proportional == false or $match['w'] * $this->_proportional_aspect == $match['h']));    
        
        return $position == false and $size;
    
    }
    
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция очистки
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    function clear() {
    
        $this->_match = null;
        $this->_anchor = null;
        $this->_img = null;
        $this->_crop_pane = null;
        $this->_crop_start = null;
        $this->set_settings();
        
    }
    
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция выставления настроек
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    function set_settings($dragging = true, $proportional = true, $proportional_aspect = 1, $min_size = [50, 50]) {
    
        $this->_dragging = $dragging;
        $this->_proportional = $proportional;
        $this->_proportional_aspect = $proportional_aspect;
        $this->_min_size = $min_size;
        
    }
    
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция обозначения нужных объектов
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    function register($anchor, $img, $crop_pane, $onUp, $onDrag) {
    
        $this->_anchor = $anchor;
        $this->_img = $img;
        $this->_crop_pane = $crop_pane;
        
        # Движение мыши
        $img->on('mouseDrag', function(UXMouseEvent $e) use () {
            if ($e->button == 'PRIMARY' and $this->_crop_start != false) {
                $this->crop_mouseDrag($e);
            }
        });
        
        # Нажали мышкой
        $img->on('mouseDown', function(UXMouseEvent $e) use ($crop_pane) {
            if ($e->button == 'PRIMARY') {
                # Выполянем расчеты
                if ($this->crop_mouseDown($e) != false) {
                    $match = $this->_match = $this->crop_match_parameters();
                    $crop_pane->maxSize = [$match['max-w'], $match['max-h']];
                }
            }
        });
        
        # Отпустили мышку 
        $img->on('mouseUp', function(UXMouseEvent $e) use ($onUp) {
            if ($e->button == 'PRIMARY' and $this->_crop_start != false) {
                call_user_func($onUp, $this->check_rect_valid());
            }
        });
        
        # Передвигаем панель выделения
        $crop_pane->on('mouseDrag', function(UXMouseEvent $e) use ($onDrag) {
            if ($e->button == 'PRIMARY' and $this->_crop_start != false) {
                $this->crop_paneMouseDrag($onDrag);
            }
        });
        
    }
    
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция создания кроп панели
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    function crop_mouseDown($e) {
    
        # Получаем объекты
        list($anchor, $crop_pane, $img, $dragging) = [$this->_anchor, $this->_crop_pane, $this->_img, $this->_dragging];
    
        # Высчитываем параметры
        $match = $this->_match = $this->crop_match_parameters();
        
        # Запоминаем начальную позицию выделения
        $crop_pane->position = [$e->position[0] + $anchor->paddingLeft, $e->position[1] + $anchor->paddingTop];
        $startX = $e->position[0] + $anchor->paddingLeft;
        $startY = $e->position[1] + $anchor->paddingTop;
        $this->_crop_start = ['x' => $startX, 'y' => $startY];
        
        # Если передана возможность передвигать выделенную область
        if ($dragging) {
            # Создаем поведение.
            $dragging = new DraggingBehaviour();
            $dragging->limitedByParent = true;
            $dragging->apply($crop_pane);
        }
        
        # Подготавливаем значения
        $max_start_X = $match['space_x'] + $match['img_new_width'];
        $max_start_Y = $match['space_y'] + $match['img_new_height'];
        
        $crop_pane->size = [0, 0];
        # Проверяем начальную точку выделения
        if ($startX < $match['space_x'] or $startY < $match['space_y'] or $startX > $max_start_X or $startY > $max_start_Y) {
            $crop_pane->visible = false;
            return false;
        } else {
            $crop_pane->visible = true;
            return ['x' => $startX, 'y' => $startY];
        }
    
    }
    
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция вычислений высоты и ширины
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    function crop_mouseDrag($e) {
        
        # Получаем объекты
        list($anchor, $crop_pane, $crop_start, $proportional, $proportional_aspect, $match) = [$this->_anchor, $this->_crop_pane, $this->_crop_start, $this->_proportional, $this->_proportional_aspect, $this->_match];
        
        $w = $e->position[0] + $anchor->paddingLeft - $crop_start['x'];
        
        # Ограничение
        $w = $match["max-w"] < $w ? $match["max-w"] : $w;
        
        # Параметры панели
        if ($proportional) {
            $h = $w * $proportional_aspect;
        } else {
            $h = $e->position[1] + $anchor->paddingTop - $crop_start['y'];
        }
        
        $crop_pane->size = [$w, $h];
        
    }
    
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция вычислений высоты и ширины
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    function crop_paneMouseDrag($onDrag) {
        
        call_user_func($onDrag, $this->check_rect_valid());
        
    }
    
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция подсчета параметров
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    function crop_match_parameters() {
        
        # Получаем объекты
        list($anchor, $crop_pane, $img) = [$this->_anchor, $this->_crop_pane, $this->_img];
        
        # Получаем координаты выделенной области
        list($x, $y, $w, $h) = [$crop_pane->position[0], $crop_pane->position[1], $crop_pane->width, $crop_pane->height];
        
        # Проверяем параметры
        if ($w == 0 or $h == 0) {
            return false;
        }
        
        # Получаем размер оригинального изображения
        $img_width =  $img->image->width;
        $img_height = $img->image->height;
        
        # Получаем размер контейнера
        $cont_width = $anchor->width;
        $cont_height = $anchor->height;
        
        # Получаем размер изображения, которое влезает в контейнер
        
        $fit = $this->fit([$img_width, $img_height], $anchor->size)["size"];
        $img_new_width = $fit[0];
        $img_new_height = $fit[1];
        
        #----------------------------------
        
        # Получаем отступы X у фотографий
        $space_x = ($cont_width - $img_new_width) / 2;
        
        # Получаем отступы Y у фотографий
        $space_y = ($cont_height - $img_new_height) / 2;
        
        #----------------------------------
        
        # Коэффициент разности изображений
        $cf_x = $img_width / $img_new_width;
        $cf_y = $img_height / $img_new_height;
        
        #----------------------------------
        
        # Устанавливаем максимально возможные размеры для области выделения
        $max_w = $img_new_width - ($x - $space_x);
        $max_h = $img_new_height - ($y - $space_y);
        
        # Переносим выделенную область на оригинальное изображение
        $x = ($x - $space_x) * $cf_x;
        $y = ($y - $space_y) * $cf_y;
        
        # Переносим ширину и высоту контейнера на оригинальное изображение
        $w *= $cf_x;
        $h *= $cf_y;
        
        # Массив данных
        $match = ['x' => $x,
                  'y' => $y,
                  'w' => $w,
                  'h' => $h,
                  'max-w' => $max_w,
                  'max-h' => $max_h,
                  'space_x' => $space_x,
                  'space_y' => $space_y,
                  'img_new_width' => $img_new_width,
                  'img_new_height' => $img_new_height
                 ];
                 
        return $match;
        
    }
    
    
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция обрезки изображения
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    /**
     * @return UXImage
     */
    function crop(UXImage $img, $x, $y, $w, $h){
        
        $canvas = new UXCanvas;
        $canvas->size = [$w, $h];
        $gc = $canvas->gc();
        $gc->drawImage($img, $x, $y, $w, $h, 0, 0, $w, $h);
        return $canvas->snapshot();
    
    }
    
    function fit($sizeObj, $bounds)
    {
        $sWidth = $bounds[1]  * ($sizeObj[0] / $sizeObj[1]);
        $sHeight = $bounds[0] * ($sizeObj[1] / $sizeObj[0]);
            
        if ($bounds[0] - $sWidth < $bounds[1] - $sHeight) {
            $result = [$bounds[0], $sHeight];
            $scale = $sHeight / $sizeObj[1];
        } else {
            $result = [$sWidth, $bounds[1]];
            $scale = $sWidth / $sizeObj[0];
        }
        
        return ["size" => $result, "scale" => $scale];
    }

}
```
{% endtab %}

{% tab title="Вызов кроп окна" %}
<pre class="language-php"><code class="lang-php"><strong># Добавляем объект на форму и назначаем ему событие и вставляем в него код
</strong>         # Загружаем изображение
        $img = new UXImageArea();
        $img->centered = true;
        $img->proportional = true;
        $img->stretch = $stretch;
        $img->smartStretch = $stretch;
        $img->image = new UXImage('res://.data/img/preview.jpg');
        
        $img->classes->add('image');
        $img->anchors = ['left' => 0, 'top' => 0, 'right' => 0, 'bottom' => 0];
        
        # Контейнер для изображения
        $anchor = new UXAnchorPane;
        $anchor->classes->add("anchor");
        $anchor->anchors = ['left' => 0, 'top' => 0, 'right' => 0, 'bottom' => 0];
        $anchor->add($img);
        
        # Бокс информации и скачивания изображения
        $anchor_box_param = new UXAnchorPane;
        $anchor_box_param->classes->add("anchor");
        $box_param = new UXHBox;
        $box_param->classes->add("box-param");
        
        # Кнопка скачать оригинал
        $label_download = new UXLabel("скачать оригинал");
        $label_download->on('click', function($e) use ($box_param) {
        
            # Начинаем скачивание изображения
            $attach_progress = new UXProgressBar;
            $attach_progress->classes->add("progress-bar");
            $attach_progress->visible = true;
            $box_param->add($attach_progress);
            $e->consume();
            
        })
        
        $box_param->add($label_download);
        $anchor_box_param->add($box_param);
        
        $content = new UXVBox([$anchor, $anchor_box_param]);
        $content->anchors = ['left' => 0, 'top' => 0, 'right' => 0, 'bottom' => 0];
        UXVBox::setVgrow($anchor, 'ALWAYS');
        UXVBox::setVgrow($content, 'ALWAYS');
        
        # Параметры модального окна
        $modal = [
                'fitToWidth' => true, # Во всю длину
                'fitToHeight' => true, # Во всю ширину
                'blur' => $this->flowPane, # Объект для размытия
                'content' => $content, # Контент
                'close_overlay' => true # Закрывать при клике мыши на overlay
                ];
        # Отображаем окно      
        app()->module("modal")->modal_dialog($this, $modal, null);
    }

    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция открытия модального окна
    #----------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @event button4.action 
     */
    function doButton4Action(UXEvent $e = null)
    {
        
        #---------------------------------------------------------------------------------
        
        # Создаем левое выезжающее меню пользователя
        $user_profile = new UXVBox;
        $user_profile->classes->add('user-profile');
        $user_profile->anchors = ['left' => 0,'right' => 0];
        
        # Проверяем на аватарку
        $user_avatar = $this->setBorderRadius($this->setImage('res://.data/img/no_avatar.png', 60, 60), 60);
        
        $lm_user_name = new UXLabel("Владимир Букреев");
        $lm_user_name->classes->addAll(['name', 'font-gray']);
        $user_profile->add($user_avatar);
        $user_profile->add($lm_user_name);
        
        # Ссылки меню
        $user_profile_links = new UXVBox;
        $user_profile_links->classes->add('links');
        $user_profile_links->anchors = ['left' => 0,'right' => 0];
        
        $li_create_dialog = new UXLabel("Создать диалог");
        $li_create_dialog->classes->addAll(['link']);
        $li_create_dialog->graphic = $this->setImage('res://.data/img/user.png', 16, 16);
        $li_create_dialog->graphicTextGap = 8;
        $li_create_group_chat = new UXLabel("Создать групповой чат");
        $li_create_group_chat->classes->addAll(['link']);
        $li_create_group_chat->graphic = $this->setImage('res://.data/img/users.png', 16, 16);
        $li_create_group_chat->graphicTextGap = 8;
        $li_create_channel = new UXLabel("Создать сообщество");
        $li_create_channel->classes->addAll(['link']);
        $li_create_channel->graphic = $this->setImage('res://.data/img/application-icon-large.png', 16, 16);
        $li_create_channel->graphicTextGap = 8;
        $li_users = new UXLabel("Пользователи");
        $li_users->classes->addAll(['link']);
        $li_users->graphic = $this->setImage('res://.data/img/magnifier-left.png', 16, 16);
        $li_users->graphicTextGap = 8;
        $li_favorites = new UXLabel("Избранное");
        $li_favorites->classes->addAll(['link']);
        $li_favorites->graphic = $this->setImage('res://.data/img/star.png', 16, 16);
        $li_favorites->graphicTextGap = 8;
        $user_profile_links->add($li_create_dialog);
        $user_profile_links->add($li_create_group_chat);
        $user_profile_links->add($li_create_channel);
        $user_profile_links->add($li_users);
        $user_profile_links->add($li_favorites);
        
        # inf
        $user_profile_inf = new UXVBox;
        $user_profile_inf->classes->add('inf');
        $user_profile_inf->anchors = ['left' => 0,'right' => 0];
        
        $li_user_profile_inf = new UXLabel('ver 6.0');
        $li_user_profile_inf->classes->addAll(['item', 'font-white-gray']);
        $user_profile_inf->add($li_user_profile_inf);
        
        # space
        $left_menu_programm_space = new UXVBox;
        UXVBox::setVgrow($left_menu_programm_space, 'ALWAYS');
        
        # Content
        $left_menu_programm_content = new UXVBox;
        $left_menu_programm_content->anchors = ['left' => 0, 'right' => 0, 'top' => 0, 'bottom' => 0];
        $left_menu_programm_content->classes->add('content');
        UXVBox::setVgrow($left_menu_programm_content, 'ALWAYS');
        
        $left_menu_programm = new UXScrollPane;
        $left_menu_programm->classes->add('left-menu-programm');
        $left_menu_programm->fitToHeight = true;
        $left_menu_programm->fitToWidth = true;
        $left_menu_programm->content = $left_menu_programm_content;
        $left_menu_programm->on('click', function($e) {
            $e->consume();
        });
        
        $left_menu_programm_content->add($user_profile);
        $left_menu_programm_content->add($user_profile_links);
        $left_menu_programm_content->add($user_profile_inf);
        $left_menu_programm_content->add($left_menu_programm_space);
        
        # Параметры модального окна
        $modal = [
            'fitToWidth' => false,
            'fitToHeight' => true,
            'blur' => $this->flowPane,
            'close_overlay' => true,
            'padding' => [0,0,0,0],
            'content' => $left_menu_programm,
            'contentFitToHeight' => true
            ];
        # Отображаем окно      
        $this->modal_dialog($this, $modal);
</code></pre>
{% endtab %}
{% endtabs %}

{% hint style="info" %}
**Внимание:** Данный класс может быть обновлён, и в него могут быть добавлены новые свойства.
{% endhint %}

