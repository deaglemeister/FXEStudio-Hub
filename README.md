# FXEdition | Сборка для DevelNext 16.7.0 
<p align="center">
  <img alt="FXEdition White" src="https://github.com/deaglemeister/FXEdition/assets/82234313/09efd85b-bab6-4214-935d-5407f063353e"  width="400">

</p>


## О модификации программного обеспечения
**FXEdition - это мощный инструмент для редактирования и обработки своих программ.**
**Содержит широкий набор функций для создания профессиональных программ, визуальных эффектов, и мощным конструктором.**
**FXEdition предоставляет пользователям удобный интерфейс, интуитивно понятный рабочий процесс и многочисленные возможности для создания.**

**Для получения дополнительной информации, пожалуйста, посетите наш телеграмм канал: [@fxedition17](https://t.me/fxedition17)**


## FXEdition модификация к DevelNext

**Преимущества использования модификации:**

- Богатый набор инструментов для редактирования и обработки программ и их редактированию.
- Современный и интуитивно понятный интерфейс
- Легко применяемые компонениы и Monaco Editor
- Это доступ к богатой библиотеке стандартных функций и классов, которые значительно упрощают и ускоряют процесс разработки. Эта библиотека предлагает множество готовых к использованию решений для обработки текста, работы с базами данных, графического интерфейса и других распространенных задач программирования.
- Поддерживает несколько языков программирования, включая PHP, Java, C++, HTML, CSS и JavaScript, что делает ее универсальным инструментом для разработки практически любого типа приложений.
- Среду предоставляет удобную систему навигации по проекту и предоставляет возможность легко добавлять, удалять и изменять файлы и папки в проекте.
- Также она имеет удобные средства для отслеживания изменений в коде и управления версиями.
DevelNext также имеет интегрированную систему отладки, включая точки останова, отображение значений переменных и трассировку стека вызовов. Это позволяет разработчикам эффективно находить и исправлять ошибки в своем коде.
- DevelNext разработан для профессионалов и любителей, желающих создавать уникальные и качественные программы.
- Но всё же это мощная и простая в использовании интегрированная среда разработки (IDE), созданная для разработчиков различных программных приложений. Эта программа предлагает широкий спектр инструментов, позволяющих эффективно создавать, тестировать и отлаживать код.

- **Мы приглашаем вас ознакомиться с этой новой версией модификации программного обеспечения FXEdition. Уверены, что она полностью превзойдет ваши ожидания и станет вашим незаменимым помощником в повседневной работе!**

## Установка среды 💿
Если вы хотите установить FXEdition без настройки среды разработки, вы можете использовать наши бинарные [**Версии**](https://github.com/deaglemeister/FXEdition/releases).
Советуем всегда качать последние версии, ведь они всегда стабильнее и новее по оптимизации :).

| Windows 7+ Zip 
| :---: 
| [x64](https://github.com/deaglemeister/FXEdition/releases/) |  |


#### Документация в реализации для сборки 💻
[Перейти в документацию](https://github.com/deaglemeister/FXEdition/blob/main/SUMMARY.md)

#### Как установить данную версию сборки? ⚠️

[Просмотреть установку сборки](https://www.youtube.com/watch?v=_IwR8deSkBo)

## Как собрать из исходников 💻

### Загрузка исходного кода:

Клонируйте с помощью `git`:

```sh
git clone https://github.com/deaglemeister/FXEdition
cd FXEdition
```

Чтобы обновить исходный код до последних изменений, запустите эту команду в папке `FXEdition`:

```sh
git fetch
git pull
```

Чтобы собрать `jar` файл из исходников, выполните команду внутри папки `FXEdition`:
```sh
jar -cMf FXE.jar ./.data ./.dn ./.forms ./.game ./.system ./ide ./JPHP-INF ./META-INF ./vendor ./LICENSE
```
Затем переместите `FXE.jre` в папку `C:\Program Files (x86)\DevelNext\lib`
