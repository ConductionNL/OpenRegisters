<?php

namespace OCA\OpenRegister\Service;

use OCA\OpenRegister\Db\Source;
use OCA\OpenRegister\Db\SourceMapper;
use OCA\OpenRegister\Db\Schema;
use OCA\OpenRegister\Db\SchemaMapper;
use OCA\OpenRegister\Db\Register;
use OCA\OpenRegister\Db\RegisterMapper;
use OCA\OpenRegister\Db\ObjectEntity;
use OCA\OpenRegister\Db\ObjectEntityMapper;

class ObjectService
{
	private $callLogMapper;

	/**
	 * The constructor sets al needed variables.
	 *
	 * @param ObjectEntityMapper  $objectEntityMapper The ObjectEntity Mapper
	 */
	public function __construct(ObjectEntityMapper $objectEntityMapper)
	{
		$this->objectEntityMapper = $objectEntityMapper;
	}

	/**
	 * Save an object 
	 *
	 * @param Register $register	The data to be saved.
	 * @param Schema $schema		The data to be saved.
	 * @param array $object			The data to be saved.
	 *
	 * @return ObjectEntity The resulting object.
	 */
	public function saveObject(Register $register, Schema $schema, array $object): ObjectEntity
	{
		// Lets see if we need to save to an internal source
		if ($register->getSource() === 'internal') {
			$objectEntity = new ObjectEntity();
			$objectEntity->setRegister($register->getId());
			$objectEntity->setSchema($schema->getId());
			$objectEntity->setObject($object);

			if (isset($object['id'])) {
				// Update existing object
				$objectEntity->setUuidId($object['id']);
				return $this->objectEntityMapper->update($objectEntity);
			} else {
				// Create new object
				$objectEntity->setUuidId(Uuid::v4());
				return $this->objectEntityMapper->insert($objectEntity);
			}
		}

		// Handle external source here if needed
		throw new \Exception('Unsupported source type');
	}

	/**
	 * Finds objects based upon a set of filters.
	 *
	 * @param array $filters The filters to compare the object to.
	 * @param array $config  The configuration that should be used by the call.
	 *
	 * @return array The objects found for given filters.
	 *
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function findObjects(array $filters, array $config): array
	{
		$client = $this->getClient(config: $config);

		$object               = self::BASE_OBJECT;
		$object['dataSource'] = $config['mongodbCluster'];
		$object['filter']     = $filters;

		// @todo Fix mongodb sort
		// if (empty($sort) === false) {
		// 	$object['filter'][] = ['$sort' => $sort];
		// }

		$returnData = $client->post(
			uri: 'action/find',
			options: ['json' => $object]
		);

		return json_decode(
			json: $returnData->getBody()->getContents(),
			associative: true
		);
	}

	/**
	 * Finds an object based upon a set of filters (usually the id)
	 *
	 * @param array $filters The filters to compare the objects to.
	 * @param array $config  The config to be used by the call.
	 *
	 * @return array The resulting object.
	 *
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function findObject(array $filters, array $config): array
	{
		$client = $this->getClient(config: $config);

		$object               = self::BASE_OBJECT;
		$object['filter']     = $filters;
		$object['dataSource'] = $config['mongodbCluster'];

		$returnData = $client->post(
			uri: 'action/findOne',
			options: ['json' => $object]
		);

		$result = json_decode(
			json: $returnData->getBody()->getContents(),
			associative: true
		);

		return $result['document'];
	}



	/**
	 * Updates an object in MongoDB
	 *
	 * @param array $filters The filter to search the object with (id)
	 * @param array $update  The fields that should be updated.
	 * @param array $config  The configuration to be used by the call.
	 *
	 * @return array The updated object.
	 *
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function updateObject(array $filters, array $update, array $config): array
	{
		$client = $this->getClient(config: $config);

		$dotUpdate = new Dot($update);

		$object                   = self::BASE_OBJECT;
		$object['filter']         = $filters;
		$object['update']['$set'] = $update;
		$object['upsert']		  = true;
		$object['dataSource']     = $config['mongodbCluster'];



			$returnData = $client->post(
				uri: 'action/updateOne',
				options: ['json' => $object]
			);

		return $this->findObject($filters, $config);
	}

	/**
	 * Delete an object according to a filter (id specifically)
	 *
	 * @param array $filters The filters to use.
	 * @param array $config  The config to be used by the call.
	 *
	 * @return array An empty array.
	 *
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function deleteObject(array $filters, array $config): array
	{
		$client = $this->getClient(config: $config);

		$object                   = self::BASE_OBJECT;
		$object['filter']         = $filters;
		$object['dataSource']     = $config['mongodbCluster'];

		$returnData = $client->post(
			uri: 'action/deleteOne',
			options: ['json' => $object]
		);

		return [];
	}

	/**
	 * Aggregates objects for search facets.
	 *
	 * @param array $filters  The filters apply to the search request.
	 * @param array $pipeline The pipeline to use.
	 * @param array $config   The configuration to use in the call.
	 * @return array
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function aggregateObjects(array $filters, array $pipeline, array $config):array
	{
		$client = $this->getClient(config: $config);

		$object               = self::BASE_OBJECT;
		$object['filter']     = $filters;
		$object['pipeline']   = $pipeline;
		$object['dataSource'] = $config['mongodbCluster'];

		$returnData = $client->post(
			uri: 'action/aggregate',
			options: ['json' => $object]
		);

		return json_decode(
			json: $returnData->getBody()->getContents(),
			associative: true
		);

	}

}
