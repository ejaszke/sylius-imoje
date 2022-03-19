<?php
declare(strict_types=1);

namespace Fronty\SyliusIMojePlugin\Controller;


use Doctrine\ORM\EntityManagerInterface;
use Fronty\SyliusIMojePlugin\Api\IMojeApi;
use Fronty\SyliusIMojePlugin\Utils\PriceFormatter;
use Imoje\Payment\Configuration;
use Payum\Core\Exception\Http\HttpException;
use Psr\Log\LoggerInterface;
use SM\StateMachine\StateMachineInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Payum\Core\Storage\StorageInterface;
use Symfony\Component\HttpFoundation\Request;
use SM\Factory\FactoryInterface;


final class NotificationController
{
    /** @var StorageInterface */
    private $gatewayConfigStore;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var PaymentRepositoryInterface */
    private $paymentRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var FactoryInterface
     */
    private $sm;

    /**
     * @var EntityManagerInterface
     */
    private $paymentMethodManager;

    /**
     * @var EntityManagerInterface
     */
    private $orderMethodManager;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        StorageInterface $gatewayConfigStore,
        PaymentRepositoryInterface $paymentRepository,
        LoggerInterface $logger,
        FactoryInterface $sm,
        EntityManagerInterface $paymentMethodManager,
        EntityManagerInterface $orderMethodManager
    ) {
        $this->gatewayConfigStore = $gatewayConfigStore;
        $this->orderRepository = $orderRepository;
        $this->paymentRepository = $paymentRepository;
        $this->logger = $logger;
        $this->sm = $sm;
        $this->paymentMethodManager = $paymentMethodManager;
        $this->orderMethodManager = $orderMethodManager;
    }

    public function processAction()
    {
        $gatewayConfiguration = $this->gatewayConfigStore->findBy(['factoryName' => 'imoje'])[0];
        $config = $gatewayConfiguration->getConfig();

        $data = IMojeApi::checkRequestNotification($config['serviceKey'], $config['serviceId']);

        /** @var OrderInterface $order */
        $order = $this->orderRepository->find($data['transaction']['orderId']);

        if (!($order->getTotal() === $data['transaction']['amount'])) {
            throw new HttpException('Wrong amount');
        }

        $payment = $order->getLastPayment();

        $states =  $data['transaction']['status'] === IMojeApi::STATUS_SETTLED
            ? [
                    'order_state' => OrderPaymentTransitions::TRANSITION_PAY,
                    'payment_state' => PaymentTransitions::TRANSITION_COMPLETE,
                ]
            : [
                'order_state' => OrderPaymentTransitions::TRANSITION_REQUEST_PAYMENT,
                'payment_state' => PaymentTransitions::TRANSITION_PROCESS,
            ];

        if (empty($states) === false) {
            $this->applyChanges($states, $payment, $order);
        }

        $response = new JsonResponse(['status' => 'ok']);
        return $response;
    }

    private function getConfiguration(): array
    {
        $gatewayConfiguration = $this->gatewayConfigStore->findBy(['factoryName' => 'imoje'])[0];

        return [
            'service_id' => (string) $gatewayConfiguration->getConfig()['serviceId'],
            'shared_key' => (string) $gatewayConfiguration->getConfig()['serviceKey'],
        ];
    }

    private function applyChanges(array $result, $payment, $order): void
    {
        $paymentStateMachine = $this->sm->get($payment, PaymentTransitions::GRAPH);
        $this->applyTransition($paymentStateMachine, $result['payment_state']);
        $this->paymentMethodManager->flush();

        $orderStateMachine = $this->sm->get($order, OrderPaymentTransitions::GRAPH);
        $this->applyTransition($orderStateMachine, $result['order_state']);
        $this->orderMethodManager->flush();
    }

    private function applyTransition(StateMachineInterface $paymentStateMachine, string $transition): void
    {
        if ($paymentStateMachine->can($transition)) {
            $paymentStateMachine->apply($transition);
        }
    }

}
