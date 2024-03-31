# Лунная анимация

## Объявление о классе

Создаём в конструкторе IDE любой объект, и добавляем ему любое событие.

{% tabs %}
{% tab title="PHP" %}
```php
# Используем use php\framework\FXEAnimationMoonlight;

# У данного класса есть три режима работы
$Mode = 'Fast'; # Быстро
$Mode = 'Medium'; # Среднее
$Mode = 'Slow'; # Медленно
       
$FirstColor = '#A32FFF'; # Задаём первый цвет
        
$SecondColor =  '#004AA1'; # Задаём второй цвет
        
$Colors = [0=>$FirstColor, 1 =>$SecondColor]; # Создание два массива содержащих два элемента
        
$this->FXEAnimationMoonlight = new FXEAnimationMoonlight(); # Создание нового объект класса 
$this->FXEAnimationMoonlight->MoonlightAnimationStart('Название объекта','Название формы' ,$Mode, $Colors); # Делаем вызов метода
```
{% endtab %}
{% endtabs %}

{% hint style="info" %}
**Внимание:** Данный класс может быть обновлён, и в него могут быть добавлены новые свойства.
{% endhint %}

