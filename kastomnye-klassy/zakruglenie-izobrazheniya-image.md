# Закругление изображения (Image)

## Объявление о классе

Создаём в конструкторе IDE любой объект, и добавляем ему любое событие.

{% tabs %}
{% tab title="Создание модуля BorderImg" %}
```php
# Создаем название модуля BorderImg

    function UI_IMAGE_BORDER($image, $radius) {   
            $rect = new UXRectangle;
            $rect->width = $image->width;
            $rect->height = $image->height;
            $rect->arcWidth = $radius*2;
            $rect->arcHeight = $radius*2;
            $image->clip = $rect;
            $circledImage = $image->snapshot();
            $image->clip = NULL;
            $rect->free();
            $image->image = $circledImage;
            return $image;
        }
```
{% endtab %}

{% tab title="Вызов модуля BorderIng" %}
```php
# Добавляем изображение на форму и назначаем форме "Перед появлением" "Появление"  и вставляем в него код

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {    
      $this->UI_IMAGE_BORDER($this->image, 6); # Задаём фотографии радиус в появление формы
      $this->UI_IMAGE_BORDER($this->imageAlt, 12); # Задаём фотографии радиус 12 в появдение формы
      $this->UI_IMAGE_BORDER($this->image3, 99); # Задаём фотографии радиус 99 в появление формы
      
# Не забываем подлкючить функцию, она находится в MainModule
    }
```
{% endtab %}
{% endtabs %}

{% hint style="info" %}
**Внимание:** Данный класс может быть обновлён, и в него могут быть добавлены новые свойства.
{% endhint %}

