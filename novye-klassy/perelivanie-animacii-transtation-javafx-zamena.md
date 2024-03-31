# Переливание анимации (transtation JavaFX замена)

## Объявление о классе

Создаём в конструкторе IDE любой объект, и добавляем ему любое событие.

{% tabs %}
{% tab title="PHP" %}
```php
# Используем use php\framework\FXEAnimationGradientFlow;

# У данного класса есть три режима работы
$Mode = 'Fast'; # Быстро
$Mode = 'Medium'; # Среднее
$Mode = 'Slow'; # Медленно
       
# Так же у данного класса есть метод перелевания цвета
$Color = '#fff'; # Тут мы задали белый цвет на его переливание

$this->FXEAnimationGradientFlow = new FXEAnimationGradientFlow(); # Создание нового объект класса 
$this->FXEAnimationGradientFlow->startAnimation('название объекта','название формы' ,$Mode, $Color,'backgroundColor') # Делаем вызов метода
```
{% endtab %}
{% endtabs %}

{% hint style="info" %}
**Внимание:** Данный класс может быть обновлён, и в него могут быть добавлены новые свойства.
{% endhint %}

