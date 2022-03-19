<?php

/*
 * This file was created by developers working at BitBag
 * Do you need more information about us and what we do? Visit our https://bitbag.io website!
 * We are hiring developers from all over the world. Join us and start your new, exciting adventure and become part of us: https://bitbag.io/career
*/

declare(strict_types=1);

namespace Fronty\SyliusIMojePlugin\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Convert;
use Sylius\Bundle\PayumBundle\Provider\PaymentDescriptionProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class ConvertPaymentAction implements ActionInterface
{
    use GatewayAwareTrait;


    public function __construct()
    {

    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getSource();

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $paymentData = $this->getPaymentData($payment);
        $customerData = $this->getCustomerData($order);

        $details = array_merge($paymentData, $customerData);

        $request->setResult($details);
    }

    public function supports($request): bool
    {
        return
            $request instanceof Convert &&
            $request->getSource() instanceof PaymentInterface &&
            $request->getTo() === 'array';
    }

    private function getPaymentData(PaymentInterface $payment): array
    {
        $paymentData = [];

        $paymentData['amount'] = $payment->getAmount();
        $paymentData['currency'] = $payment->getCurrencyCode();



        return $paymentData;
    }

    private function getCustomerData(OrderInterface $order): array
    {
        $customerData = [];

        if (null !== $address = $order->getShippingAddress()) {
            $customerData['customerPhone'] = $address->getPhoneNumber();
            $customerData['customerFirstName'] = $address->getFirstName();
            $customerData['customerLastName'] = $address->getLastName();
            $customerData['customerEmail'] = $order->getCustomer()->getEmail();
        }

        $customerData['orderId'] = $order->getId();

        return $customerData;
    }


}
