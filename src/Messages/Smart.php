<?php

namespace Nylas\Messages;

use Nylas\Labels\Label;
use Nylas\Utilities\API;
use Nylas\Folders\Folder;
use Nylas\Utilities\Helper;
use Nylas\Utilities\Options;
use Nylas\Utilities\Validator as V;

/**
 * ----------------------------------------------------------------------------------
 * Nylas Messages Smart Update
 * ----------------------------------------------------------------------------------
 *
 * @author lanlin
 * @change 2021/09/22
 */
class Smart
{
    // ------------------------------------------------------------------------------

    /**
     * @var \Nylas\Utilities\Options
     */
    private Options $options;

    // ------------------------------------------------------------------------------

    /**
     * Search constructor.
     *
     * @param \Nylas\Utilities\Options $options
     */
    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    // ------------------------------------------------------------------------------

    /**
     * add labels to message
     *
     * @param string $messageId
     * @param mixed  $labels    string|string[]
     *
     * @return array
     */
    public function addLabels(string $messageId, mixed $labels): array
    {
        return $this->updateLabels($messageId, $labels);
    }

    // ------------------------------------------------------------------------------

    /**
     * remove labels from message
     *
     * @param string $messageId
     * @param mixed  $labels    string|string[]
     *
     * @return array
     */
    public function removeLabels(string $messageId, mixed $labels): array
    {
        return $this->updateLabels($messageId, [], $labels);
    }

    // ------------------------------------------------------------------------------

    /**
     * archive message
     *
     * @param string $messageId
     *
     * @return array
     */
    public function archive(string $messageId): array
    {
        return Helper::isLabel($this->options) ?
        $this->updateLabels($messageId, null, ['inbox']) :
        $this->updateFolder($messageId, 'archive');
    }

    // ------------------------------------------------------------------------------

    /**
     * unarchive message
     *
     * @param string $messageId
     *
     * @return array
     */
    public function unarchive(string $messageId): array
    {
        return Helper::isLabel($this->options) ?
        $this->updateLabels($messageId, ['inbox'], ['archive']) :
        $this->updateFolder($messageId, 'inbox');
    }

    // ------------------------------------------------------------------------------

    /**
     * move message to trash
     *
     * @param string $messageId
     *
     * @return array
     */
    public function trash(string $messageId): array
    {
        return Helper::isLabel($this->options) ?
        $this->updateLabels($messageId, ['trash'], ['inbox']) :
        $this->updateFolder($messageId, 'trash');
    }

    // ------------------------------------------------------------------------------

    /**
     * move from "label|folder" to other "label|folder" by name
     *
     * @param string $messageId
     * @param string $from
     * @param string $goto
     *
     * @return array
     */
    public function move(string $messageId, string $from, string $goto): array
    {
        return Helper::isLabel($this->options) ?
        $this->updateLabels($messageId, [$goto], [$from]) :
        $this->updateFolder($messageId, $goto);
    }

    // ------------------------------------------------------------------------------

    /**
     * set message to start
     *
     * @param mixed $messageId string|string[]
     *
     * @return array
     */
    public function star(mixed $messageId): array
    {
        $params = ['starred' => true];

        return $this->updateOneField($messageId, $params);
    }

    // ------------------------------------------------------------------------------

    /**
     * set message to un-star
     *
     * @param mixed $messageId string|string[]
     *
     * @return array
     */
    public function unstar(mixed $messageId): array
    {
        $params = ['starred' => false];

        return $this->updateOneField($messageId, $params);
    }

    // ------------------------------------------------------------------------------

    /**
     * mark message as read
     *
     * @param mixed $messageId string|string[]
     *
     * @return array
     */
    public function markAsRead(mixed $messageId): array
    {
        $params = ['unread' => false];

        return $this->updateOneField($messageId, $params);
    }

    // ------------------------------------------------------------------------------

    /**
     * mark message as unread
     *
     * @param mixed $messageId string|string[]
     *
     * @return array
     */
    public function markAsUnread(mixed $messageId): array
    {
        $params = ['unread' => true];

        return $this->updateOneField($messageId, $params);
    }

    // ------------------------------------------------------------------------------

    /**
     * move message to folder by id
     *
     * @param mixed  $messageId string|string[]
     * @param string $folderId
     *
     * @return array
     */
    public function moveToFolder(mixed $messageId, string $folderId): array
    {
        Helper::checkProviderUnit($this->options, false);

        V::doValidate(V::stringType()->notEmpty(), $folderId);

        $params = ['folder_id' => $folderId];

        return $this->updateOneField($messageId, $params);
    }

    // ------------------------------------------------------------------------------

    /**
     * move message to labels by id
     *
     * @param mixed $messageId string|string[]
     * @param array $labelIds
     *
     * @return array
     */
    public function moveToLabel(mixed $messageId, array $labelIds): array
    {
        Helper::checkProviderUnit($this->options, true);

        V::doValidate(V::simpleArray(V::stringType()->notEmpty()), $labelIds);

        $params = ['label_ids' => $labelIds];

        return $this->updateOneField($messageId, $params);
    }

    // ------------------------------------------------------------------------------

    /**
     * update message folder
     *
     * @param string $messageId
     * @param string $folder
     *
     * @return array
     */
    private function updateFolder(string $messageId, string $folder): array
    {
        $folderId   = null;
        $allFolders = (new Folder($this->options))->returnAllFolders();

        foreach ($allFolders as $row)
        {
            if ($row['name'] === $folder)
            {
                $folderId = $row['id'];
                break;
            }
        }

        return $this->moveToLabel($messageId, $folderId);
    }

    // ------------------------------------------------------------------------------

    /**
     * update message labels
     *
     * @param string $messageId
     * @param mixed  $add       string|string[]
     * @param mixed  $del       string|string[]
     *
     * @return array
     */
    private function updateLabels(string $messageId, mixed $add = [], mixed $del = []): array
    {
        $tmpLabels = [];
        $allLabels = (new Label($this->options))->getLabelsList();
        $emailData = (new Message($this->options))->returnAMessage($messageId);
        $nowLabels = $emailData[$messageId]['labels'] ?? [];

        $add = Helper::fooToArray($add);
        $del = Helper::fooToArray($del);

        // check all labels
        foreach ($allLabels as $label)
        {
            $secA = !empty($label['name']) && \in_array($label['name'], $add, true);
            $secB = empty($label['name'])  && \in_array($label['display_name'], $add, true);

            if ($secA || $secB)
            {
                $tmpLabels[] = $label['id'];
            }
        }

        // check current message labels
        foreach ($nowLabels as $label)
        {
            $secA = !empty($label['name']) && \in_array($label['name'], $del, true);
            $secB = empty($label['name'])  && \in_array($label['display_name'], $del, true);

            if ($secA || $secB)
            {
                continue;
            }

            $tmpLabels[] = $label['id'];
        }

        return $this->moveToLabel($messageId, $tmpLabels);
    }

    // ------------------------------------------------------------------------------

    /**
     * update the specific field of message
     *
     * @param mixed $messageId string|string[]
     * @param array $params
     *
     * @return array
     */
    private function updateOneField(mixed $messageId, array $params): array
    {
        $messageId = Helper::fooToArray($messageId);

        V::doValidate(V::simpleArray(V::stringType()->notEmpty()), $messageId);

        $queues = [];

        foreach ($messageId as $id)
        {
            $request = $this->options
                ->getAsync()
                ->setPath($id)
                ->setFormParams($params)
                ->setHeaderParams($this->options->getAuthorizationHeader());

            $queues[] = static function () use ($request)
            {
                return $request->put(API::LIST['oneMessage']);
            };
        }

        $pools = $this->options->getAsync()->pool($queues, false);

        return Helper::concatPoolInfos($messageId, $pools);
    }

    // ------------------------------------------------------------------------------
}
