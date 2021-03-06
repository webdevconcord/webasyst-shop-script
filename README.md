# Модуль ConcordPay для Webasyst Shop-Script

Для работы модуля у вас должно быть установлено приложение **Shop-Script** для **Webasyst**.

## Установка

1. Скачайте последнюю версию платёжного модуля.

2. Распакуйте архив на сервере в каталог с установленной **Webasyst Shop-Script**.
   Пример пути для файлов модуля: *{Webasyst_Shop-Script_root}/wa-plugins/payment/concordpay*.

3. При необходимости установите права доступа для файлов модуля.

4. Зайдите как администратор в **Webasyst Shop-Script** и в разделе *«Магазин → Настройки → Оплата»* в выпадающем списке
   *«Добавить способ оплаты»* выберите **Concordpay**.
   
5. Заполните поля модуля данными, полученными от платёжной системы:
   - Идентификатор продавца (Merchant ID);
   - Секретный ключ (Secret Key).

6. Отметьте способы доставки, которые можно будет оплатить при помощи данного платёжного метода.

7. Сохраните настройки.

Модуль готов к работе.

*Модуль протестирован для работы с Webasyst 2.2.1.613, Shop-Script 8.17.1 и PHP 7.2.*