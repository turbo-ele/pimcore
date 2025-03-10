<?php
declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\AdminBundle\Controller\Admin;

use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Pimcore\Bundle\AdminBundle\DependencyInjection\PimcoreAdminExtension;
use Pimcore\Db;
use Pimcore\Event\AdminEvents;
use Pimcore\Event\Model\ResolveElementEvent;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Element;
use Pimcore\Model\Version;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 *
 * @internal
 */
class ElementController extends AdminController
{
    /**
     * @Route("/element/lock-element", name="pimcore_admin_element_lockelement", methods={"PUT"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function lockElementAction(Request $request): Response
    {
        Element\Editlock::lock($request->request->getInt('id'), $request->request->get('type'));

        return $this->adminJson(['success' => true]);
    }

    /**
     * @Route("/element/unlock-element", name="pimcore_admin_element_unlockelement", methods={"PUT"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function unlockElementAction(Request $request): Response
    {
        Element\Editlock::unlock((int)$request->get('id'), $request->get('type'));

        return $this->adminJson(['success' => true]);
    }

    /**
     * @Route("/element/unlock-elements", name="pimcore_admin_element_unlockelements", methods={"POST"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function unlockElementsAction(Request $request): Response
    {
        $request = json_decode($request->getContent(), true) ?? [];
        foreach ($request['elements'] as $elementIdentifierData) {
            Element\Editlock::unlock((int)$elementIdentifierData['id'], $elementIdentifierData['type']);
        }

        return $this->adminJson(['success' => true]);
    }

    /**
     * Returns the element data denoted by the given type and ID or path.
     *
     * @Route("/element/get-subtype", name="pimcore_admin_element_getsubtype", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getSubtypeAction(Request $request): JsonResponse
    {
        $idOrPath = trim($request->query->get('id', ''));
        $type = $request->query->get('type');

        $event = new ResolveElementEvent($type, $idOrPath);
        \Pimcore::getEventDispatcher()->dispatch($event, AdminEvents::RESOLVE_ELEMENT);
        $idOrPath = $event->getId();
        $type = $event->getType();

        if (is_numeric($idOrPath)) {
            $el = Element\Service::getElementById($type, (int) $idOrPath);
        } else {
            if ($type == 'document') {
                $el = Document\Service::getByUrl($idOrPath);
            } else {
                $el = Element\Service::getElementByPath($type, $idOrPath);
            }
        }

        if ($el) {
            $subtype = null;
            if ($el instanceof Asset || $el instanceof Document) {
                $subtype = $el->getType();
            } elseif ($el instanceof DataObject\Concrete) {
                $subtype = $el->getClassName();
            } elseif ($el instanceof DataObject\Folder) {
                $subtype = 'folder';
            }

            return $this->adminJson([
                'subtype' => $subtype,
                'id' => $el->getId(),
                'type' => $type,
                'success' => true,
            ]);
        } else {
            return $this->adminJson([
                'success' => false,
            ]);
        }
    }

    protected function processNoteTypesFromParameters(string $parameterName): \Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse
    {
        $config = $this->getParameter($parameterName);
        $result = [];
        foreach ($config as $configEntry) {
            $result[] = [
                'name' => $configEntry,
            ];
        }

        return $this->adminJson(['noteTypes' => $result]);
    }

    /**
     * @Route("/element/note-types", name="pimcore_admin_element_notetypes", methods={"GET"})
     *
     * @param Request $request
     *
     * @return \Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse|JsonResponse
     */
    public function noteTypes(Request $request): \Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse|JsonResponse
    {
        switch ($request->get('ctype')) {
            case 'document':
                return $this->processNoteTypesFromParameters(PimcoreAdminExtension::PARAM_DOCUMENTS_NOTES_EVENTS_TYPES);
            case 'asset':
                return $this->processNoteTypesFromParameters(PimcoreAdminExtension::PARAM_ASSETS_NOTES_EVENTS_TYPES);
            case 'object':
                return $this->processNoteTypesFromParameters(PimcoreAdminExtension::PARAM_DATAOBJECTS_NOTES_EVENTS_TYPES);
            default:
                return $this->adminJson(['noteTypes' => []]);
        }
    }

    /**
     * @Route("/element/note-list", name="pimcore_admin_element_notelist", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function noteListAction(Request $request): JsonResponse
    {
        $this->checkPermission('notes_events');

        if ($request->query->get('xaction') === 'destroy') {
            $data = $this->decodeJson($request->request->get('data'));
            $success = false;
            if (($note = Element\Note::getById($data['id'])) && !$note->getLocked()) {
                $note->delete();
                $success = true;
            }

            return $this->adminJson(['success' => $success]);
        }

        $list = new Element\Note\Listing();

        $offset = (int) $request->get('start', 0);
        $limit = $request->get('limit');
        $limit = $limit ? (int) $limit : null;

        $list->setLimit($limit);
        $list->setOffset($offset);

        $sortingSettings = \Pimcore\Bundle\AdminBundle\Helper\QueryParams::extractSortingSettings(array_merge($request->request->all(), $request->query->all()));
        if ($sortingSettings['orderKey'] && $sortingSettings['order']) {
            $list->setOrderKey($sortingSettings['orderKey']);
            $list->setOrder($sortingSettings['order']);
        } else {
            $list->setOrderKey(['date', 'id']);
            $list->setOrder(['DESC', 'DESC']);
        }

        $conditions = [];
        $filterText = $request->get('filterText');

        if ($filterText) {
            $conditions[] = '('
                . '`title` LIKE ' . $list->quote('%'. $filterText .'%')
                . ' OR `description` LIKE ' . $list->quote('%'.$filterText.'%')
                . ' OR `type` LIKE ' . $list->quote('%'.$filterText.'%')
                . ' OR `user` IN (SELECT `id` FROM `users` WHERE `name` LIKE ' . $list->quote('%'.$filterText.'%') . ')'
                . " OR DATE_FORMAT(FROM_UNIXTIME(`date`), '%Y-%m-%d') LIKE " . $list->quote('%'.$filterText.'%')
                . ')';
        }

        $filterJson = $request->get('filter');
        if ($filterJson) {
            $db = Db::get();
            $filters = $this->decodeJson($filterJson);
            $propertyKey = 'property';
            $comparisonKey = 'operator';

            foreach ($filters as $filter) {
                $operator = '=';

                if ($filter['type'] == 'string') {
                    $operator = 'LIKE';
                } elseif ($filter['type'] == 'numeric') {
                    if ($filter[$comparisonKey] == 'lt') {
                        $operator = '<';
                    } elseif ($filter[$comparisonKey] == 'gt') {
                        $operator = '>';
                    } elseif ($filter[$comparisonKey] == 'eq') {
                        $operator = '=';
                    }
                } elseif ($filter['type'] == 'date') {
                    if ($filter[$comparisonKey] == 'lt') {
                        $operator = '<';
                    } elseif ($filter[$comparisonKey] == 'gt') {
                        $operator = '>';
                    } elseif ($filter[$comparisonKey] == 'eq') {
                        $operator = '=';
                    }
                    $filter['value'] = strtotime($filter['value']);
                } elseif ($filter[$comparisonKey] == 'list') {
                    $operator = '=';
                } elseif ($filter[$comparisonKey] == 'boolean') {
                    $operator = '=';
                    $filter['value'] = (int) $filter['value'];
                }
                // system field
                $value = ($filter['value']??'');
                if ($operator == 'LIKE') {
                    $value = '%' . $value . '%';
                }

                if ($filter[$propertyKey] == 'user') {
                    $conditions[] = '`user` IN (SELECT `id` FROM `users` WHERE `name` LIKE ' . $list->quote($value) . ')';
                } else {
                    if ($filter['type'] == 'date' && $filter[$comparisonKey] == 'eq') {
                        $maxTime = $value + (86400 - 1); //specifies the top point of the range used in the condition
                        $dateCondition = '`' . $filter[$propertyKey] . '` ' . ' BETWEEN ' . $db->quote($value) . ' AND ' . $db->quote($maxTime);
                        $conditions[] = $dateCondition;
                    } else {
                        $conditions[] = $db->quoteIdentifier($filter[$propertyKey]).' '.$operator.' '.$db->quote($value);
                    }
                }
            }
        }

        if ($request->get('cid') && $request->get('ctype')) {
            $conditions[] = '(cid = ' . $list->quote($request->get('cid')) . ' AND ctype = ' . $list->quote($request->get('ctype')) . ')';
        }

        if (!empty($conditions)) {
            $condition = implode(' AND ', $conditions);
            $list->setCondition($condition);
        }

        $list->load();

        $notes = [];

        foreach ($list->getNotes() as $note) {
            $e = Element\Service::getNoteData($note);
            $notes[] = $e;
        }

        return $this->adminJson([
            'data' => $notes,
            'success' => true,
            'total' => $list->getTotalCount(),
        ]);
    }

    /**
     * @Route("/element/note-add", name="pimcore_admin_element_noteadd", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function noteAddAction(Request $request): JsonResponse
    {
        $this->checkPermission('notes_events');

        $note = new Element\Note();
        $note->setCid((int) $request->get('cid'));
        $note->setCtype($request->get('ctype'));
        $note->setDate(time());
        $note->setTitle($request->get('title'));
        $note->setDescription($request->get('description'));
        $note->setType($request->get('type'));
        $note->setLocked(false);
        $note->save();

        return $this->adminJson([
            'success' => true,
        ]);
    }

    /**
     * @Route("/element/find-usages", name="pimcore_admin_element_findusages", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function findUsagesAction(Request $request): JsonResponse
    {
        $element = null;
        if ($request->query->get('id')) {
            $element = Element\Service::getElementById($request->query->get('type'), $request->query->getInt('id'));
        } elseif ($request->query->get('path')) {
            $element = Element\Service::getElementByPath($request->query->get('type'), $request->query->get('path'));
        }

        $results = [];
        $success = false;
        $hasHidden = false;
        $total = 0;
        $limit = (int)$request->get('limit', 50);
        $offset = (int)$request->get('start', 0);

        if ($element instanceof Element\ElementInterface) {
            $total = $element->getDependencies()->getRequiredByTotalCount();

            if ($request->get('sort')) {
                $sort = json_decode($request->get('sort'))[0];
                $orderBy = $sort->property;
                $orderDirection = $sort->direction;
            } else {
                $orderBy = null;
                $orderDirection = null;
            }

            $queryOffset = $offset;
            $queryLimit = $limit;

            while (count($results) < min($limit, $total) && $queryOffset < $total) {
                $elements = $element->getDependencies()
                    ->getRequiredByWithPath($queryOffset, $queryLimit, $orderBy, $orderDirection);

                foreach ($elements as $el) {
                    $item = Element\Service::getElementById($el['type'], $el['id']);

                    if ($item instanceof Element\ElementInterface) {
                        if ($item->isAllowed('list')) {
                            $results[] = $el;
                        } else {
                            $hasHidden = true;
                        }
                    }
                }

                $queryOffset += count($elements);
                $queryLimit = $limit - count($results);
            }

            $success = true;
        }

        return $this->adminJson([
            'data' => $results,
            'total' => $total,
            'hasHidden' => $hasHidden,
            'success' => $success,
        ]);
    }

    /**
     * @Route("/element/get-replace-assignments-batch-jobs", name="pimcore_admin_element_getreplaceassignmentsbatchjobs", methods={"GET"})
     *
     * @param Request $request
     *
     * @return \Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse
     */
    public function getReplaceAssignmentsBatchJobsAction(Request $request): \Pimcore\Bundle\AdminBundle\HttpFoundation\JsonResponse
    {
        $element = null;

        if ($request->query->get('id')) {
            $element = Element\Service::getElementById($request->query->get('type'), $request->query->getInt('id'));
        } elseif ($request->query->get('path')) {
            $element = Element\Service::getElementByPath($request->query->get('type'), $request->query->get('path'));
        }

        if ($element instanceof Element\ElementInterface) {
            return $this->adminJson([
                'success' => true,
                'jobs' => $element->getDependencies()->getRequiredBy(),
            ]);
        } else {
            return $this->adminJson(['success' => false], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @Route("/element/replace-assignments", name="pimcore_admin_element_replaceassignments", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function replaceAssignmentsAction(Request $request): JsonResponse
    {
        $success = false;
        $message = '';
        $element = Element\Service::getElementById($request->request->get('type'), $request->request->getInt('id'));
        $sourceEl = Element\Service::getElementById($request->request->get('sourceType'), $request->request->getInt('sourceId'));
        $targetEl = Element\Service::getElementById($request->request->get('targetType'), $request->request->getInt('targetId'));

        if ($element && $sourceEl && $targetEl
            && $request->get('sourceType') == $request->get('targetType')
            && $sourceEl->getType() == $targetEl->getType()
            && $element->isAllowed('save')
        ) {
            $rewriteConfig = [
                $request->get('sourceType') => [
                    $sourceEl->getId() => $targetEl->getId(),
                ],
            ];

            if ($element instanceof Document) {
                $element = Document\Service::rewriteIds($element, $rewriteConfig);
            } elseif ($element instanceof DataObject\AbstractObject) {
                $element = DataObject\Service::rewriteIds($element, $rewriteConfig);
            } elseif ($element instanceof Asset) {
                $element = Asset\Service::rewriteIds($element, $rewriteConfig);
            }

            $element->setUserModification($this->getAdminUser()->getId());
            $element->save();

            $success = true;
        } else {
            $message = 'source-type and target-type do not match';
        }

        return $this->adminJson([
            'success' => $success,
            'message' => $message,
        ]);
    }

    /**
     * @Route("/element/unlock-propagate", name="pimcore_admin_element_unlockpropagate", methods={"PUT"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function unlockPropagateAction(Request $request): JsonResponse
    {
        $success = false;

        $element = Element\Service::getElementById($request->request->get('type'), $request->request->getInt('id'));
        if ($element) {
            $element->unlockPropagate();
            $success = true;
        }

        return $this->adminJson([
            'success' => $success,
        ]);
    }

    /**
     * @Route("/element/type-path", name="pimcore_admin_element_typepath", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function typePathAction(Request $request): JsonResponse
    {
        $id = $request->query->getInt('id');
        $type = $request->query->get('type');
        $data = [];

        if ($type === 'asset') {
            $element = Asset::getById($id);
        } elseif ($type === 'document') {
            $element = Document::getById($id);
        } else {
            $element = DataObject::getById($id);
        }

        if (!$element) {
            $data['success'] = false;

            return $this->adminJson($data);
        }

        $typePath = Element\Service::getTypePath($element);

        $data['success'] = true;
        $data['index'] = method_exists($element, 'getIndex') ? (int) $element->getIndex() : 0;
        $data['idPath'] = Element\Service::getIdPath($element);
        $data['typePath'] = $typePath;
        $data['fullpath'] = $element->getRealFullPath();

        if ($type !== 'asset') {
            $sortIndexPath = Element\Service::getSortIndexPath($element);
            $data['sortIndexPath'] = $sortIndexPath;
        }

        return $this->adminJson($data);
    }

    /**
     * @Route("/element/version-update", name="pimcore_admin_element_versionupdate", methods={"PUT"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function versionUpdateAction(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request->get('data'));

        $version = Version::getById($data['id']);

        if ($data['public'] != $version->getPublic() || $data['note'] != $version->getNote()) {
            $version->setPublic($data['public']);
            $version->setNote($data['note']);
            $version->save();
        }

        return $this->adminJson(['success' => true]);
    }

    /**
     * @Route("/element/get-nice-path", name="pimcore_admin_element_getnicepath", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function getNicePathAction(Request $request): JsonResponse
    {
        $source = $this->decodeJson($request->get('source'));
        if ($source['type'] != 'object') {
            throw new \Exception('currently only objects as source elements are supported');
        }
        $result = [];
        $id = $source['id'];
        $source = DataObject\Concrete::getById($id);
        if ($request->get('context')) {
            $context = $this->decodeJson($request->get('context'));
        } else {
            $context = [];
        }

        $ownerType = $context['containerType'];
        $fieldname = $context['fieldname'];

        $fd = $this->getNicePathFormatterFieldDefinition($source, $context);

        $targets = $this->decodeJson($request->get('targets'));

        $result = $this->convertResultWithPathFormatter($source, $context, $result, $targets);

        if ($request->request->getBoolean('loadEditModeData')) {
            $idProperty = $request->get('idProperty', 'id');
            $methodName = 'get' . ucfirst($fieldname);
            if ($ownerType == 'object' && method_exists($source, $methodName)) {
                $data = $source->$methodName();
                $editModeData = $fd->getDataForEditmode($data, $source);
                // Inherited values show as an empty array
                if (is_array($editModeData) && !empty($editModeData)) {
                    foreach ($editModeData as $relationObjectAttribute) {
                        $relationObjectAttribute['$$nicepath'] =
                            isset($relationObjectAttribute[$idProperty]) && isset($result[$relationObjectAttribute[$idProperty]]) ? $result[$relationObjectAttribute[$idProperty]] : null;
                        $result[$relationObjectAttribute[$idProperty]] = $relationObjectAttribute;
                    }
                } else {
                    foreach ($result as $resultItemId => $resultItem) {
                        $result[$resultItemId] = ['$$nicepath' => $resultItem];
                    }
                }
            } else {
                Logger::error('Loading edit mode data is not supported for ownertype: ' . $ownerType);
            }
        }

        return $this->adminJson(['success' => true, 'data' => $result]);
    }

    /**
     * @Route("/element/get-versions", name="pimcore_admin_element_getversions", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function getVersionsAction(Request $request): JsonResponse
    {
        $id = (int)$request->get('id');
        $type = $request->get('elementType');
        $allowedTypes = ['asset', 'document', 'object'];

        if ($id && in_array($type, $allowedTypes)) {
            $element = Model\Element\Service::getElementById($type, $id);
            if ($element) {
                if ($element->isAllowed('versions')) {
                    $schedule = $element->getScheduledTasks();
                    $schedules = [];
                    foreach ($schedule as $task) {
                        if ($task->getActive()) {
                            $schedules[$task->getVersion()] = $task->getDate();
                        }
                    }

                    //only load auto-save versions from current user
                    $list = new Version\Listing();
                    $list->setLoadAutoSave(true);
                    $list->setCondition('cid = ? AND ctype = ? AND (autoSave=0 OR (autoSave=1 AND userId = ?)) ', [
                        $element->getId(),
                        Element\Service::getElementType($element),
                        $this->getAdminUser()->getId(),
                    ])
                        ->setOrderKey('date')
                        ->setOrder('ASC');

                    $versions = $list->load();

                    $versions = Model\Element\Service::getSafeVersionInfo($versions);
                    $versions = array_reverse($versions); //reverse array to sort by ID DESC
                    foreach ($versions as &$version) {
                        $version['scheduled'] = null;
                        if (array_key_exists($version['id'], $schedules)) {
                            $version['scheduled'] = $schedules[$version['id']];
                        }
                    }

                    return $this->adminJson(['versions' => $versions]);
                } else {
                    throw $this->createAccessDeniedException('Permission denied, ' . $type . ' id [' . $id . ']');
                }
            } else {
                throw $this->createNotFoundException($type . ' with id [' . $id . "] doesn't exist");
            }
        }

        throw $this->createNotFoundException('Element type not found');
    }

    /**
     * @Route("/element/delete-draft", name="pimcore_admin_element_deletedraft", methods={"DELETE"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function deleteDraftAction(Request $request): JsonResponse
    {
        $version = Version::getById((int) $request->get('id'));
        if ($version) {
            $version->delete();
        }

        return $this->adminJson(['success' => true]);
    }

    /**
     * @Route("/element/delete-version", name="pimcore_admin_element_deleteversion", methods={"DELETE"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function deleteVersionAction(Request $request): JsonResponse
    {
        $version = Model\Version::getById((int) $request->get('id'));
        $version->delete();

        return $this->adminJson(['success' => true]);
    }

    /**
     * @Route("/element/delete-all-versions", name="pimcore_admin_element_deleteallversion", methods={"DELETE"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function deleteAllVersionAction(Request $request): JsonResponse
    {
        $elementId = $request->request->getInt('id');
        $elementModificationdate = $request->request->get('date');

        $versions = new Model\Version\Listing();
        $versions->setCondition('cid = ' . $versions->quote($elementId) . ' AND date <> ' . $versions->quote($elementModificationdate));

        foreach ($versions->load() as $vkey => $version) {
            $version->delete();
        }

        return $this->adminJson(['success' => true]);
    }

    /**
     * @Route("/element/get-requires-dependencies", name="pimcore_admin_element_getrequiresdependencies", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getRequiresDependenciesAction(Request $request): JsonResponse
    {
        $id = $request->query->getInt('id');
        $type = $request->query->get('elementType');
        $allowedTypes = ['asset', 'document', 'object'];
        $offset = (int) $request->get('start', 0);
        $limit = (int) $request->get('limit', 25);

        if ($id && in_array($type, $allowedTypes)) {
            $element = Model\Element\Service::getElementById($type, $id);
            $dependencies = $element->getDependencies();

            if ($element instanceof Model\Element\ElementInterface) {
                $dependenciesResult = Model\Element\Service::getRequiresDependenciesForFrontend($dependencies, $offset, $limit);

                $dependenciesResult['start'] = $offset;
                $dependenciesResult['limit'] = $limit;
                $dependenciesResult['total'] = $dependencies->getRequiresTotalCount();

                return $this->adminJson($dependenciesResult);
            }
        }

        return $this->adminJson(false);
    }

    /**
     * @Route("/element/get-required-by-dependencies", name="pimcore_admin_element_getrequiredbydependencies", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getRequiredByDependenciesAction(Request $request): JsonResponse
    {
        $id = $request->query->getInt('id');
        $type = $request->query->get('elementType');
        $allowedTypes = ['asset', 'document', 'object'];
        $offset = (int) $request->get('start', 0);
        $limit = (int) $request->get('limit', 25);

        if ($id && in_array($type, $allowedTypes)) {
            $element = Model\Element\Service::getElementById($type, $id);
            $dependencies = $element->getDependencies();

            if ($element instanceof Model\Element\ElementInterface) {
                $dependenciesResult = Model\Element\Service::getRequiredByDependenciesForFrontend($dependencies, $offset, $limit);

                $dependenciesResult['start'] = $offset;
                $dependenciesResult['limit'] = $limit;
                $dependenciesResult['total'] = $dependencies->getRequiredByTotalCount();

                return $this->adminJson($dependenciesResult);
            }
        }

        return $this->adminJson(false);
    }

    /**
     * @Route("/element/get-predefined-properties", name="pimcore_admin_element_getpredefinedproperties", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getPredefinedPropertiesAction(Request $request): JsonResponse
    {
        $properties = [];
        $type = $request->get('elementType');
        $query = $request->get('query');
        $allowedTypes = ['asset', 'document', 'object'];

        if (in_array($type, $allowedTypes, true)) {
            $list = new Model\Property\Predefined\Listing();
            $list->setFilter(function (Model\Property\Predefined $predefined) use ($type, $query) {
                if (!str_contains($predefined->getCtype(), $type)) {
                    return false;
                }
                if ($query && stripos($this->trans($predefined->getName()), $query) === false) {
                    return false;
                }

                return true;
            });

            foreach ($list->getProperties() as $type) {
                $properties[] = $type->getObjectVars();
            }
        }

        return $this->adminJson(['properties' => $properties]);
    }

    /**
     * @Route("/element/analyze-permissions", name="pimcore_admin_element_analyzepermissions", methods={"POST"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function analyzePermissionsAction(Request $request): Response
    {
        $userId = $request->request->getInt('userId');
        if ($userId) {
            $userList = [];
            if ($user = Model\User::getById($userId)) {
                $userList[] = $user;
            }
        } else {
            $userList = new Model\User\Listing();
            $userList->setCondition('`type` = ?', ['user']);
            $userList = $userList->load();
        }

        $elementType = $request->request->get('elementType');
        $elementId = $request->request->getInt('elementId');

        $element = Element\Service::getElementById($elementType, $elementId);

        $result = Element\PermissionChecker::check($element, $userList);

        return $this->adminJson(
            [
                'data' => $result,
                'success' => true,
            ]
        );
    }

    /**
     * @param DataObject\Concrete $source
     * @param array $context
     *
     * @return bool|DataObject\ClassDefinition\Data|null
     *
     * @throws \Exception
     */
    protected function getNicePathFormatterFieldDefinition(DataObject\Concrete $source, array $context): DataObject\ClassDefinition\Data|bool|null
    {
        $ownerType = $context['containerType'];
        $fieldname = $context['fieldname'];
        $fd = null;

        if ($ownerType == 'object') {
            $subContainerType = isset($context['subContainerType']) ? $context['subContainerType'] : null;
            if ($subContainerType) {
                $subContainerKey = $context['subContainerKey'];
                $subContainer = $source->getClass()->getFieldDefinition($subContainerKey);
                if (method_exists($subContainer, 'getFieldDefinition')) {
                    $fd = $subContainer->getFieldDefinition($fieldname);
                }
            } else {
                $fd = $source->getClass()->getFieldDefinition($fieldname);
            }
        } elseif ($ownerType == 'localizedfield') {
            $localizedfields = $source->getClass()->getFieldDefinition('localizedfields');
            if ($localizedfields instanceof DataObject\ClassDefinition\Data\Localizedfields) {
                $fd = $localizedfields->getFieldDefinition($fieldname);
            }
        } elseif ($ownerType == 'objectbrick') {
            $fdBrick = DataObject\Objectbrick\Definition::getByKey($context['containerKey']);
            $fd = $fdBrick->getFieldDefinition($fieldname);
        } elseif ($ownerType == 'fieldcollection') {
            $containerKey = $context['containerKey'];
            $fdCollection = DataObject\Fieldcollection\Definition::getByKey($containerKey);
            if (($context['subContainerType'] ?? null) === 'localizedfield') {
                /** @var DataObject\ClassDefinition\Data\Localizedfields $fdLocalizedFields */
                $fdLocalizedFields = $fdCollection->getFieldDefinition('localizedfields');
                $fd = $fdLocalizedFields->getFieldDefinition($fieldname);
            } else {
                $fd = $fdCollection->getFieldDefinition($fieldname);
            }
        }

        return $fd;
    }

    /**
     * @param DataObject\Concrete $source
     * @param array $context
     * @param array $result
     * @param array $targets
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function convertResultWithPathFormatter(DataObject\Concrete $source, array $context, array $result, array $targets): array
    {
        $fd = $this->getNicePathFormatterFieldDefinition($source, $context);

        if ($fd instanceof DataObject\ClassDefinition\PathFormatterAwareInterface) {
            $formatter = $fd->getPathFormatterClass();

            if (null !== $formatter) {
                $pathFormatter = DataObject\ClassDefinition\Helper\PathFormatterResolver::resolvePathFormatter(
                    $fd->getPathFormatterClass()
                );

                if ($pathFormatter instanceof DataObject\ClassDefinition\PathFormatterInterface) {
                    $result = $pathFormatter->formatPath($result, $source, $targets, [
                        'fd' => $fd,
                        'context' => $context,
                    ]);
                }
            }
        }

        return $result;
    }
}
