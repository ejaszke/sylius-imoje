<?php

declare(strict_types=1);

namespace Fronty\SyliusIMojePlugin\Api;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\Http\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @author Ondrej Seliga <ondrej@seliga.cz>
 */
final class IMojeApi implements IMojeApiInterface
{

	/** @var ArrayObject */
	private $options = [
		'merchantId' => '',
		'serviceId' => '',
		'serviceKey' => '',
		'environment' => '',
		'visibleMethod' => ''
	];

	/**
	 * @param array $options [string $merchantId, string $serviceId, string $serviceKey, string $environment (prouction|sandbox)]
	 */
	public function __construct(array $options) {
		$options = ArrayObject::ensureArrayObject($options);
        $options->defaults($this->options);
        $options->validateNotEmpty([
            'merchantId',
            'serviceId',
            'serviceKey',
            'environment',
            'visibleMethod'
        ]);
        $this->options = $options;
	}

    public static function checkRequestNotification($serviceKey, $serviceId)
    {
        $request = Request::createFromGlobals();
        $payload = $request->getContent();

        $header = (explode(';', $request->headers->get('X-IMoje-Signature')));

        $algoFromNotification = explode('=', $header[3]);
        $algoFromNotification = $algoFromNotification[1];

        $headerSignature = explode('=', $header[2]);

        if ($headerSignature[1] !== hash($algoFromNotification, $payload . $serviceKey)) {
            throw new BadRequestHttpException('Wrong signature' .hash($algoFromNotification, $payload . $serviceKey) );
        }

        $payloadDecoded = json_decode($payload, true);

        if ($payloadDecoded['transaction']['serviceId'] !== $serviceId) {
            throw new BadRequestHttpException('Wrong service id');
        }

        return $payloadDecoded;
    }

	/**
	 * @return string
	 */
	public function getApiEndpoint(): string
	{
		return ($this->options['environment'] === 'sandbox') ?
			self::URL_SANDBOX :
			self::URL_PRODUCTION;
	}

	/**
	 * @param array $data Transaction fields
	 * @return array
	 *
	 * @see https://www.imoje.pl/developerzy/paywall-api#1
	 */
	public function createFields(array $data): array
	{
		$data = array_merge($data, (array)$this->options);
		$requiredFields = [
			'serviceId',
			'merchantId',
			'amount',
			'currency',
			'orderId',
			'customerFirstName',
			'customerLastName',
			'customerEmail'
		];
		$optionalFields = [
			'customerPhone',
			'urlSuccess',
			'urlFailure',
			'urlReturn',
			'simp',
			'orderDescription',
			'visibleMethod',
			'twistoData'
		];
		$allFields = array_merge($requiredFields, $optionalFields);
		$result = [];
		foreach ($allFields as $field) {
			if (array_key_exists($field, $data)) $result[$field] = $data[$field];
		}
		$result = ArrayObject::ensureArrayObject($result);
		$result->validateNotEmpty($requiredFields);
		$result['signature'] = $this->createSignature((array)$result, $this->options['serviceKey'], 'sha256');
		return $result->toUnsafeArray();
	}

	/**
	 * Calculates signature hash.
	 * @param array $fields Transaction fields.
	 * @param string $serviceKey Service key from configuration.
	 * @return string
	 *
	 * @see https://www.imoje.pl/developerzy/paywall-api#1 (section "Przykład wyliczenia sygnatury")
	 */
    function createSignature($orderData, $serviceKey, $hashMethod)
    {
        ksort($orderData);
        $data = '';
        foreach($orderData as $key => $value) {
            $data .= $key . '=' . $value . "&";
        }
        return hash($hashMethod, $data . $serviceKey). ';' . $hashMethod;
    }

    function getKey(): string {
        return $this->options['serviceKey'];
    }
}
