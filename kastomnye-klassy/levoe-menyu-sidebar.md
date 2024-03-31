# Левое меню (SideBar)

## Объявление о классе

Создаём в конструкторе IDE любой объект, и добавляем ему любое событие.

{% tabs %}
{% tab title="Вызов модульного окна" %}
```php
# Добавляем объект на форму и назначаем ему событие и вставляем в него код
# CSS Code нужно использовать из вызова модульного окна

       #---------------------------------------------------------------------------------
        
        # Создаем левое выезжающее меню пользователя
        $user_profile = new UXVBox;
        $user_profile->classes->add('user-profile');
        $user_profile->anchors = ['left' => 0,'right' => 0];
        
        # Проверяем на аватарку
        $user_avatar = $this->setBorderRadius($this->setImage('res://.data/img/no_avatar.png', 60, 60), 60);
        
        $lm_user_name = new UXLabel("Допустим имя ваше");
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
```
{% endtab %}
{% endtabs %}

{% hint style="info" %}
**Внимание:** Данный класс может быть обновлён, и в него могут быть добавлены новые свойства.
{% endhint %}

