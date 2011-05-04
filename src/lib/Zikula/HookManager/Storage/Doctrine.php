<?php
/**
 * Copyright 2010 Zikula Foundation
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 * @package Zikula
 * @subpackage HookManager
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */
/**
 * Doctrine class.
 */
class Zikula_HookManager_Storage_Doctrine implements Zikula_HookManager_StorageInterface
{
    const PROVIDER = 'p';
    const SUBSCRIBER = 's';

    private $runtimeHandlers = array();
    public function registerSubscriber($owner, $subOwner, $areaName, $areaType, $category, $eventName)
    {
        $areaId = $this->registerArea($areaName, self::SUBSCRIBER, $owner, $subOwner, $category);

        // Now we have an areaId we can register a subscriber
        $subscriber = new Zikula_Doctrine_Model_HookSubscriber();
        $subscriber->merge(array(
                'owner' => $owner,
                'subowner' => $subOwner,
                'sareaid' => $areaId,
                'hooktype' => $areaType,
                'category' => $category,
                'eventname' => $eventName,
        ));
        $subscriber->save();
    }

    public function getSubscriberByEventName($eventName)
    {
        return Doctrine_Query::create()->select()
                ->where('eventname = ?', $eventName)
                ->from('Zikula_Doctrine_Model_HookSubscriber')
                ->fetchArray();
    }

    public function unregisterSubscriberByArea($areaName)
    {
        $areaId = $this->getAreaId($areaName);

        if (!$areaId) {
            return;
        }

        // delete subscriber entry
        Doctrine_Query::create()->delete()
                ->where('sareaid = ?', $areaId)
                ->from('Zikula_Doctrine_Model_HookSubscriber')
                ->execute();

        // remove bindings
        Doctrine_Query::create()->delete()
                ->where('sareaid = ?', $areaId)
                ->from('Zikula_Doctrine_Model_HookBinding')
                ->execute();

        // check if areas have been orphaned
        $subscribers = Doctrine_Query::create()->select()
                ->where('sareaid = ?', $areaId)
                ->from('Zikula_Doctrine_Model_HookSubscriber');
        if (!$subscribers->count()) {
            Doctrine_Query::create()->delete()
                    ->where('id = ?', $areaId)
                    ->from('Zikula_Doctrine_Model_HookArea')
                    ->execute();
        }
    }

    public function registerProvider($name, $owner, $subOwner, $areaName, $hookType, $category, $className, $method, $serviceId=null)
    {
        $areaId = $this->registerArea($areaName, self::PROVIDER, $owner, $subOwner, $category);

        $provider = new Zikula_Doctrine_Model_HookProvider();
        $provider->merge(array(
                'name' => $name,
                'owner' => $owner,
                'subowner' => $subOwner,
                'pareaid' => $areaId,
                'hooktype' => $hookType,
                'category' => $category,
                'classname' => $className,
                'method' => $method,
                'serviceid' => $serviceId,
                'priority' => 10, // obsolete
        ));
        $provider->save();
    }

    public function unregisterProviderByArea($areaName)
    {
        $areaId = $this->getAreaId($areaName);

        if (!$areaId) {
            return;
        }

        // delete subscriber entry
        Doctrine_Query::create()->delete()
                ->where('pareaid = ?', $areaId)
                ->from('Zikula_Doctrine_Model_HookProvider')
                ->execute();

        // remove bindings
        Doctrine_Query::create()->delete()
                ->where('pareaid = ?', $areaId)
                ->from('Zikula_Doctrine_Model_HookBinding')
                ->execute();
    }

    public function getSubscribersByOwner($owner)
    {
        return Doctrine_Query::create()->select()
                ->where('owner = ?', $owner)
                ->from('Zikula_Doctrine_Model_HookSubscriber')
                ->fetchArray();
    }

    public function getSubscriberAreasByOwner($owner)
    {
        return (array)Doctrine_Query::create()->select('DISTINCT areaname')
                ->where('owner = ?', $owner)
                ->andWhere('areatype = ?', self::SUBSCRIBER)
                ->from('Zikula_Doctrine_Model_HookArea')
                ->execute(array(), Doctrine_Core::HYDRATE_SINGLE_SCALAR);
    }

    public function getProviderAreasByOwner($owner)
    {
        return (array)Doctrine_Query::create()->select('DISTINCT areaname')
                ->where('owner = ?', $owner)
                ->andWhere('areatype = ?', self::PROVIDER)
                ->from('Zikula_Doctrine_Model_HookArea')
                ->execute(array(), Doctrine_Core::HYDRATE_SINGLE_SCALAR);
    }

    private function generateRuntimeHandlers()
    {
        // truncate runtime
        Doctrine_Query::create()->delete()
                ->from('Zikula_Doctrine_Model_HookRuntime')
                ->execute();

        foreach ($this->getBindings() as $binding) {
            $this->addRuntimeHandlers($binding['sareaid'], $binding['pareaid']);
        }
    }

    private function addRuntimeHandlers($subscriberAreaId, $providerAreaId)
    {
        $sa = Doctrine_Core::getTable('Zikula_Doctrine_Model_HookArea')
                ->findBy('id', $subscriberAreaId);
        $pa = Doctrine_Core::getTable('Zikula_Doctrine_Model_HookArea')
                ->findBy('id', $providerAreaId);

        $subscribers = Doctrine_Query::create()->select()
                ->where('sareaid = ?', $subscriberAreaId)
                ->from('Zikula_Doctrine_Model_HookSubscriber')
                ->fetchArray();

        if (!$subscribers) {
            return false;
        }

        foreach ($subscribers as $subscriber) {
            $provider = Doctrine_Query::create()->select()
                    ->where('pareaid = ?', $providerAreaId)
                    ->andWhere('hooktype = ?', $subscriber['hooktype'])
                    ->from('Zikula_Doctrine_Model_HookProvider')
                    ->fetchArray();

            if ($provider) {
                $provider = $provider[0];
                $binding = new Zikula_Doctrine_Model_HookRuntime();
                $binding->merge(array(
                        'sowner' => $sa->getFirst()->get('owner'),
                        'subsowner' => $sa->getFirst()->get('subowner'),
                        'powner' => $pa->getFirst()->get('owner'),
                        'subpowner' => $pa->getFirst()->get('subowner'),
                        'sareaid' => $subscriberAreaId,
                        'pareaid' => $providerAreaId,
                        'eventname' => $subscriber['eventname'],
                        'handlername' => $provider['name'],
                        'classname' => $provider['classname'],
                        'method' => $provider['method'],
                        'serviceid' => $provider['serviceid'],
                        'priority' => 10,
                ));
                $binding->save();
            }
        }
    }

    public function getRuntimeHandlers()
    {
        $this->runtimeHandlers = Doctrine_Query::create()->select()
                ->from('Zikula_Doctrine_Model_HookRuntime')
                ->fetchArray();
        return $this->runtimeHandlers;
    }

    public function bindSubscriber($subscriberArea, $providerArea)
    {
        $sa = Doctrine_Core::getTable('Zikula_Doctrine_Model_HookArea')
                ->findBy('areaname', $subscriberArea);
        $pa = Doctrine_Core::getTable('Zikula_Doctrine_Model_HookArea')
                ->findBy('areaname', $providerArea);

        $binding = new Zikula_Doctrine_Model_HookBinding();
        $binding->merge(array(
                'sowner' => $sa->getFirst()->get('owner'),
                'subsowner' => $sa->getFirst()->get('subowner'),
                'powner' => $pa->getFirst()->get('owner'),
                'subpowner' => $pa->getFirst()->get('subowner'),
                'sareaid' => $sa->getFirst()->get('id'),
                'pareaid' => $pa->getFirst()->get('id'),
                'category' => $sa->getFirst()->get('category'),
                'sortorder' => 999,
        ));
        $binding->save();

        $this->generateRuntimeHandlers();
    }

    public function unbindSubscriber($subscriberArea, $providerArea)
    {
        $sa = Doctrine_Core::getTable('Zikula_Doctrine_Model_HookArea')
                ->findBy('areaname', $subscriberArea);
        $pa = Doctrine_Core::getTable('Zikula_Doctrine_Model_HookArea')
                ->findBy('areaname', $providerArea);
        $subscriberAreaId = $sa->getFirst()->get('id');
        $providerAreaId = $pa->getFirst()->get('id');

        Doctrine_Query::create()->delete()
                ->where('pareaid = ?', $providerAreaId)
                ->andWhere('sareaid = ?', $subscriberAreaId)
                ->from('Zikula_Doctrine_Model_HookBinding')
                ->execute();

        $this->generateRuntimeHandlers();
    }

    private function getBindings()
    {
        return Doctrine_Query::create()->select()
                ->from('Zikula_Doctrine_Model_HookBinding')
                ->orderBy('sareaid ASC, sortorder ASC')
                ->fetchArray();
    }

    public function getBindingsFor($areaName)
    {
        $area = Doctrine_Core::getTable('Zikula_Doctrine_Model_HookArea')
                        ->findBy('areaname', $areaName)->toArray();

        if (!$area) {
            return array();
        }

        $area = $area[0];
        if ($area['areatype'] == self::PROVIDER) {
            $table = 'Zikula_Doctrine_Model_HookProvider';
            $areaIdField = 'pareaid';
        } else if ($area['areatype'] == self::SUBSCRIBER) {
            $table = 'Zikula_Doctrine_Model_HookSubscriber';
            $areaIdField = 'sareaid';
        }

        $results = Doctrine_Query::create()->select()//$areaIdField)
                ->from('Zikula_Doctrine_Model_HookBinding')
                ->where("$areaIdField = ?", $area['id'])
                ->fetchArray();

        $areas = array();
        foreach ($results as $result) {
            $area = Doctrine_Core::getTable('Zikula_Doctrine_Model_HookArea')
                            ->findBy('id', $result['pareaid'])->toArray();
            $areas[] = array('areaname' => $area[0]['areaname'], 'category' => $area[0]['category']);
        }

        return $areas;
    }

    public function setBindOrder($subscriberAreaName, array $providerAreaNames)
    {
        $sareaId = Doctrine_Core::getTable('Zikula_Doctrine_Model_HookArea')
                ->findBy('areaname', $subscriberAreaName)
                ->getFirst()
                ->get('id');

        // convert provider areanames to ids
        $providerAreaIds = array();
        foreach ($providerAreaNames as $name) {
            $providerAreaIds[] = Doctrine_Core::getTable('Zikula_Doctrine_Model_HookArea')
                            ->findBy('areaname', $name)
                            ->getFirst()->get('id');
        }

        // sort bindings in order of appearance from $providerAreaIds
        $counter = 1;
        foreach ($providerAreaIds as $id) {
            var_dump(Doctrine_Query::create()
                            ->update('Zikula_Doctrine_Model_HookBinding b')
                            ->set('b.sortorder', $counter)
                            ->where("b.sareaid = $sareaId")
                            ->andWhere("b.pareaid = $id")
                            ->execute()
            );
            $counter++;
        }
    }

    public function getAreaIdByEventName($eventName)
    {
        foreach ($this->runtimeHandlers as $handler) {
            if ($handler['eventname'] == $eventName) {
                return $handler['sareaid'];
            }
        }

        return false;
    }

    private function registerArea($areaName, $areaType, $owner, $subOwner, $category)
    {
        if ($areaType !== self::PROVIDER && $areaType !== self::SUBSCRIBER) {
            throw new InvalidArgumentException('$areaType must be "p" or "s"');
        }

        // if there is an area registered, if not, create it.
        $areaId = $this->getAreaId($areaName);
        if (!$areaId) {
            // There is no area id so create one.
            $subscriberArea = new Zikula_Doctrine_Model_HookArea();
            $subscriberArea['areaname'] = $areaName;
            $subscriberArea['owner'] = $owner;
            $subscriberArea['subowner'] = $subOwner;
            $subscriberArea['areatype'] = $areaType;
            $subscriberArea['category'] = $category;
            $subscriberArea->save();
            $areaId = $subscriberArea['id'];
        }

        return $areaId;
    }

    private function getAreaId($areaName)
    {
        $id = Doctrine_Core::getTable('Zikula_Doctrine_Model_HookArea')
                ->findBy('areaname', $areaName);
        if (!$id->count()) {
            return false;
        }
        return $id->getFirst()->get('id');
    }

}