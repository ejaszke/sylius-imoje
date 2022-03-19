<?php

declare(strict_types=1);

namespace Fronty\SyliusIMojePlugin\Action;

use Fronty\SyliusIMojePlugin\Api\IMojeApi;
use Fronty\SyliusIMojePlugin\Api\IMojeApiInterface;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpPostRedirect;
use Payum\Core\Request\Capture;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use Payum\Core\Security\GenericTokenFactoryInterface;
use Payum\Core\Security\TokenInterface;

/**
 * @author Ondrej Seliga <ondrej@seliga.cz>
 */
final class CaptureAction implements ActionInterface, GatewayAwareInterface, ApiAwareInterface, GenericTokenFactoryAwareInterface
{
	use GatewayAwareTrait;
	use ApiAwareTrait;
    private ?GenericTokenFactoryInterface $tokenFactory;

    public function __construct()
    {
        $this->apiClass = IMojeApi::class;
    }

    public function setGenericTokenFactory(GenericTokenFactoryInterface $genericTokenFactory = null): void
    {
        $this->tokenFactory = $genericTokenFactory;
    }

	/**
	 * @param Capture $request
	 * @throws RequestNotSupportedException
	 */
	public function execute($request)
	{
		RequestNotSupportedException::assertSupports($this, $request);

		$model = $request->getModel();
		$model = ArrayObject::ensureArrayObject($model);

        $details = $request->getModel();
        /** @var TokenInterface $token */
		$token = $request->getToken();

        $notifyToken = $this->tokenFactory->createNotifyToken($token->getGatewayName(), $token->getDetails());

		$model['urlSuccess'] = $token->getAfterUrl(). '&status=' . IMojeApiInterface::STATUS_PENDING;
		$model['urlFailure'] = $token->getAfterUrl() . '&status=' . IMojeApiInterface::STATUS_CANCELED;
		
		throw new HttpPostRedirect(
			$this->api->getApiEndpoint(),
			$this->api->createFields((array)$model)
		);
	}

	/**
     * {@inheritdoc}
     */
    public function supports($request) {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess;
    }

}
