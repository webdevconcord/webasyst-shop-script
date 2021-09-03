<?php

/**
 * Class concordpayPayment
 *
 * @property string $order_id
 * @property array $request
 */
class concordpayPayment extends waPayment implements waIPayment
{
    /**
     * @var string
     */
    private $url = 'https://pay.concord.ua/api/';

    const CONCORDPAY_TRANSACTION_APPROVED = 'Approved';

    const CONCORDPAY_SIGNATURE_SEPARATOR = ';';

    const RESPONSE_TYPE_REVERSE = 'reverse';
    const RESPONSE_TYPE_PAYMENT = 'payment';

    /**
     * @var string
     */
    private $pattern = '/^(\w[\w\d]+)_([\w\d]+)_(.+)$/';

    /**
     * @var string %app_id%_%merchant_id%_%order_id%
     */
    private $template = '%s_%s_%s';

    /**
     * @var string[]
     */
    protected $keysForResponseSignature = array(
        'merchantAccount',
        'orderReference',
        'amount',
        'currency'
    );

    /**
     * @var string[]
     */
    protected $keysForSignature = array(
        'merchant_id',
        'order_id',
        'amount',
        'currency_iso',
        'description'
    );

    /**
     * @var string[]
     */
    protected $operationType = array(
        'payment',
        'reverse'
    );

    /**
     * @return string[]
     */
    public function allowedCurrency()
    {
        return array('UAH');
    }

    /**
     * @param array $payment_form_data
     * @param waOrder $order_data
     * @param false $auto_submit
     * @return string|null
     * @throws SmartyException
     * @throws waException
     * @throws waPaymentException
     */
    public function payment($payment_form_data, $order_data, $auto_submit = false)
    {
        $order = waOrder::factory($order_data);
        if (!in_array($order->currency, $this->allowedCurrency())) {
            throw new waPaymentException('Invalid currency');
        }

        $contact = $order->getContact();
        [$phone] = ($contact !== null) ? $contact->get('phone', 'value') : [];

        /**
         * Check phone
         */
        $phone = str_replace(array('+', ' ', '(', ')', '-'), array('', '', '', '', ''), $phone);
        if (strlen($phone) == 10) {
            $phone = '38' . $phone;
        } elseif (strlen($phone) == 11) {
            $phone = '3' . $phone;
        }

        $formFields['operation']    = 'Purchase';
        $formFields['merchant_id']  = $this->merchant_account;
        $formFields['amount']       = str_replace(',', '.', round($order_data['total'], 2));
        $formFields['order_id']     = sprintf($this->template, $this->app_id, $this->merchant_id, $order->id);
        $formFields['currency_iso'] = $order->currency;
        $formFields['description']  = $this->_w('Payment by card on the site') . ' ' .
            htmlspecialchars($_SERVER["HTTP_HOST"]) . ', ' . $order->contact_name . ', ' . $phone;
        $formFields['signature']    = $this->getRequestSignature($formFields);
        $formFields['add_params']   = [];

        $formFields['approve_url']  = $this->getAdapter()->getBackUrl();
        $formFields['decline_url']  = $this->getAdapter()->getBackUrl(waAppPayment::URL_DECLINE);
        $formFields['cancel_url']   = $this->getAdapter()->getBackUrl(waAppPayment::URL_CHECKOUT);
        $formFields['callback_url'] = $this->getRelayUrl() . '?transaction_result=result';
        // Statistics.
        $formFields['client_first_name'] = $order->getContactField('firstname') ?? '';
        $formFields['client_last_name']  = $order->getContactField('lastname') ?? '';
        $formFields['email']             = $order->contact_email ?? '';
        $formFields['phone']             = $phone ?? '';

        $view = wa()->getView();
        $view->assign('form_fields', $formFields);
        $view->assign('form_url', $this->getEndpointUrl());
        $view->assign('auto_submit', $auto_submit);

        if (!empty($_POST) && !empty($_POST['merchantSignature'])) {
            return null;
        }

        if (!empty($_POST) && !empty($_POST['reason'])) {
            $view->assign('message', $_POST['reason']);
            return $view->fetch($this->path . '/templates/payment_message.html');
        }

        return $view->fetch($this->path . '/templates/payment.html');
    }

    /**
     * Инициализация плагина для обработки вызовов от платежной системы.
     *
     * Для обработки вызовов по URL вида /payments.php/concordpay/* необходимо определить
     * соответствующее приложение и идентификатор, чтобы правильно инициализировать настройки плагина.
     * @param array $request Данные запроса (массив $_REQUEST)
     * @return waPayment
     */
    protected function callbackInit($request)
    {
        if (!isset($request['operation'])) {
            // Needed to receive JSON request data.
            $request = $this->getRequest();
        }
        $this->request = $request;

        $order_id_string = !empty($request['orderReference']) ? $request['orderReference'] : null;
        $matches = array();
        if (preg_match($this->pattern, $order_id_string, $matches)) {
            $this->app_id      = $matches[1];
            $this->merchant_id = (int)$matches[2];
            $this->order_id    = (int)$matches[3];
        }

        return parent::callbackInit($request);
    }

    public function capture()
    {
    }

    /**
     * Обработка вызовов платежной системы.
     *
     * Проверяются параметры запроса, и при необходимости вызывается обработчик приложения.
     * Настройки плагина уже проинициализированы и доступны в коде метода.
     *
     * @param array $request Данные запроса (массив $_REQUEST), полученного от платежной системы
     * @return array Ассоциативный массив необязательных параметров результата обработки вызова:
     *                       'redirect' => URL для перенаправления пользователя
     *                       'template' => путь к файлу шаблона, который необходимо использовать для формирования веб-страницы, отображающей результат обработки вызова платежной системы;
     *                       укажите false, чтобы использовать прямой вывод текста
     *                       если не указано, используется системный шаблон, отображающий строку 'OK'
     *                       'header'   => ассоциативный массив HTTP-заголовков (в форме 'header name' => 'header value'),
     *                       которые необходимо отправить в браузер пользователя после завершения обработки вызова,
     *                       удобно для случаев, когда кодировка символов или тип содержимого отличны от UTF-8 и text/html
     *
     *     Если указан путь к шаблону, возвращаемый результат в исходном коде шаблона через переменную $result variable;
     *     параметры, переданные методу, доступны в массиве $params.
     * @throws waException
     * @throws waPaymentException
     */
    public function callbackHandler($request)
    {
        if (!isset($request['operation'])) {
            // Needed to receive JSON request data.
            $request = $this->getRequest();
        }
        $url = null;

        if (!(int)$this->order_id) {
            throw new waPaymentException($this->_w('Invalid order id'));
        }

        $sign = $this->getResponseSignature($request);
        if (!isset($request["merchantSignature"]) || $request["merchantSignature"] !== $sign) {
            throw new waPaymentException($this->_w('Invalid signature'));
        }

        if (!isset($request['type']) || !in_array($request['type'], $this->operationType, true)) {
            throw new waPaymentException($this->_w('Unknown operation type'));
        }

        if ($request['transactionStatus'] === self::CONCORDPAY_TRANSACTION_APPROVED) {
            $transaction_data = $this->formalizeData($request);
            if ($request['type'] === self::RESPONSE_TYPE_PAYMENT) {
                //Ordinary payment.
                $transaction_data['state']  = self::STATE_CAPTURED;
                $transaction_data['type']   = self::OPERATION_AUTH_CAPTURE;
                $transaction_data['result'] = (int)true;

                $transaction_data = $this->saveTransaction($transaction_data, $request);
                $result           = $this->execAppCallback(self::CALLBACK_PAYMENT, $transaction_data);
            } elseif ($request['type'] === self::RESPONSE_TYPE_REVERSE) {
                // Refunded payment.
                $transaction_data['state']  = self::STATE_REFUNDED;
                $transaction_data['type']   = self::OPERATION_REFUND;

                $transaction_data = $this->saveTransaction($transaction_data, $request);
                $result           = $this->execAppCallback(self::CALLBACK_REFUND, $transaction_data);
            }

            if (!empty($result['error'])) {
                throw new waPaymentException($this->_w('Forbidden (validate error):') . ' ' . $result['error']);
            }
        }

        return array(
            'template' => false
        );
    }

    /**
     * Конвертирует исходные данные о транзакции, полученные от платежной системы,
     * в формат, удобный для сохранения в базе данных.
     *
     * @param $transaction_raw_data
     * @return array $transaction_data Форматированные данные
     */
    protected function formalizeData($transaction_raw_data)
    {
        $transaction_data = parent::formalizeData($transaction_raw_data);

        $transaction_data['native_id']   = $transaction_raw_data['transactionId'];
        $transaction_data['order_id']    = $this->order_id;
        $transaction_data['amount']      = ifempty($transaction_raw_data['amount'], '');
        $transaction_data['currency_id'] = $transaction_raw_data['currency'];

        return $transaction_data;
    }

    /**
     * @return string
     */
    private function getEndpointUrl()
    {
        return $this->url;
    }

    /**
     * @param $option
     * @param $keys
     * @return string
     */
    public function getSignature($option, $keys)
    {
        $hash = array();
        foreach ($keys as $dataKey) {
            if (!isset($option[$dataKey])) {
                continue;
            }
            if (is_array($option[$dataKey])) {
                foreach ($option[$dataKey] as $v) {
                    $hash[] = $v;
                }
            } else {
                $hash [] = $option[$dataKey];
            }
        }
        $hash = implode(self::CONCORDPAY_SIGNATURE_SEPARATOR, $hash);

        return hash_hmac('md5', $hash, $this->secret_key);
    }

    /**
     * @param $options
     * @return string
     */
    public function getRequestSignature($options)
    {
        return $this->getSignature($options, $this->keysForSignature);
    }

    /**
     * @param $options
     * @return string
     */
    public function getResponseSignature($options)
    {
        return $this->getSignature($options, $this->keysForResponseSignature);
    }

    /**
     * @return mixed
     */
    protected function getRequest()
    {
        return json_decode(file_get_contents("php://input"), true);
    }
}
