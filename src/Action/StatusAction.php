<?php

declare(strict_types=1);

namespace Fronty\SyliusIMojePlugin\Action;

use BitBag\SyliusPrzelewy24Plugin\Bridge\Przelewy24BridgeInterface;
use Fronty\SyliusIMojePlugin\Api\IMojeApiInterface;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\GetStatusInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Bridge\Spl\ArrayObject;
use Sylius\Component\Core\Model\PaymentInterface;


final class StatusAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

	/**
	 * @param GetStatusInterface $request
	 * @throws RequestNotSupportedException
	 */
	public function execute($request)
	{
        RequestNotSupportedException::assertSupports($this, $request);

        $details = $request->getModel();

        $this->gateway->execute($httpRequest = new GetHttpRequest());

        if (isset($httpRequest->query['status']) &&
            $httpRequest->query['status'] === IMojeApiInterface::STATUS_CANCELED
        ) {
            $details['imoje_status'] = IMojeApiInterface::STATUS_CANCELED;
            $request->markCanceled();

            return;
        }

        if (false === isset($details['imoje_status'])) {
            $request->markNew();

            return;
        }

        if (IMojeApiInterface::STATUS_SETTLED === $details['imoje_status']) {
            $request->markCaptured();

            return;
        }

        if (IMojeApiInterface::STATUS_PENDING === $details['imoje_status']) {
            $request->markPending();

            return;
        }

        if (IMojeApiInterface::STATUS_ERROR === $details['imoje_status']) {
            $request->markFailed();

            return;
        }


        $request->markUnknown();
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports($request) {
		return $request instanceof GetStatusInterface &&
			$request->getModel() instanceof \ArrayAccess;
	}
}
