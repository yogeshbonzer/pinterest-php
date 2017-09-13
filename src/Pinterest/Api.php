<?php

/*
 * This file is part of the Pinterest PHP library.
 *
 * (c) Hans Ott <hansott@hotmail.be>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.md.
 *
 * Source: https://github.com/hansott/pinterest-php
 */

namespace Pinterest;

use Pinterest\Objects\Pin;
use Pinterest\Objects\User;
use Pinterest\Http\Request;
use Pinterest\Http\Response;
use Pinterest\Objects\Board;
use InvalidArgumentException;
use Pinterest\Objects\PagedList;
use Pinterest\Http\Exceptions\RateLimitedReached;

/**
 * The api client.
 *
 * @author Hans Ott <hansott@hotmail.be>
 * @author Toon Daelman <spinnewebber_toon@hotmail.com>
 */
class Api
{
    /**
     * The authentication client.
     *
     * @var Authentication
     */
    private $auth;

    /**
     * The constructor.
     *
     * @param Authentication $auth The authentication client.
     */
    public function __construct(Authentication $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Processes a response.
     *
     * @param Response $response  The response object.
     * @param callable $processor The response processor.
     *
     * @return Response The response
     */
    private function processResponse(Response $response, $processor)
    {
        if ($response->ok()) {
            $result = $processor($response);
            $response->setResult($result);
        }

        return $response;
    }

    /**
     * Execute the given http request.
     *
     * @param Request       $request
     * @param callable|null $processor
     *
     * @throws RateLimitedReached
     *
     * @return Response The response
     */
    public function execute(Request $request, $processor = null)
    {
        $response = $this->auth->execute($request);

        if ($response->rateLimited()) {
            throw new RateLimitedReached($response);
        }

        if (is_callable($processor)) {
            $response = $this->processResponse($response, $processor);
        }

        return $response;
    }

    /**
     * Fetch a single user and processes the response.
     *
     * @param Request $request
     *
     * @return Response The response
     */
    private function fetchUser(Request $request)
    {
        $request->setFields(User::fields());

        return $this->execute($request, function (Response $response) {
            $mapper = new Mapper(new User());

            return $mapper->toSingle($response);
        });
    }

    /**
     * Fetch a single board and processes the response.
     *
     * @param Request $request
     *
     * @throws RateLimitedReached
     *
     * @return Response The response
     */
    private function fetchBoard(Request $request)
    {
        $request->setFields(Board::fields());

        return $this->execute($request, function (Response $response) {
            $mapper = new Mapper(new Board());

            return $mapper->toSingle($response);
        });
    }

    /**
     * Fetch a single pin and processes the response.
     *
     * @param Request $request
     *
     * @throws RateLimitedReached
     *
     * @return Response The response
     */
    private function fetchPin(Request $request)
    {
        $request->setFields(Pin::fields());

        return $this->execute($request, function (Response $response) {
            $mapper = new Mapper(new Pin());

            return $mapper->toSingle($response);
        });
    }

    /**
     * Fetch multiple boards and processes the response.
     *
     * @param Request  $request
     * @param string[] $fields
     *
     * @throws RateLimitedReached
     *
     * @return Response The response
     */
    private function fetchMultipleBoards(Request $request, array $fields = null)
    {
        $fields = $fields ? $fields : Board::fields();
        $request->setFields($fields);

        return $this->execute($request, function (Response $response) {
            $mapper = new Mapper(new Board());

            return $mapper->toList($response);
        });
    }

    /**
     * Fetch multiple users and processes the response.
     *
     * @param Request $request
     *
     * @throws RateLimitedReached
     *
     * @return Response The response
     */
    private function fetchMultipleUsers(Request $request)
    {
        $request->setFields(User::fields());

        return $this->execute($request, function (Response $response) {
            $mapper = new Mapper(new User());

            return $mapper->toList($response);
        });
    }

    /**
     * Fetches multiple pins and processes the response.
     *
     * @param Request $request
     * @param $fields array The fields to require.
     *
     * @throws RateLimitedReached
     *
     * @return Response The response
     */
    private function fetchMultiplePins(Request $request, array $fields = null)
    {
        $fields = $fields ? $fields : Pin::fields();
        $request->setFields($fields);

        return $this->execute($request, function (Response $response) {
            $mapper = new Mapper(new Pin());

            return $mapper->toList($response);
        });
    }

    /**
     * Get a user.
     *
     * @param string $usernameOrId The username or identifier of the user.
     *
     * @return Response The response
     */
    public function getUser($usernameOrId)
    {
        if (empty($usernameOrId)) {
            throw new InvalidArgumentException('The username or id should not be empty.');
        }

        $request = new Request('GET', sprintf('users/%s/', $usernameOrId));

        return $this->fetchUser($request);
    }

    /**
     * Get a board.
     *
     * @param string $boardId The board id.
     *
     * @return Response The response
     */
    public function getBoard($boardId)
    {
        if (empty($boardId)) {
            throw new InvalidArgumentException('The board id should not be empty.');
        }

        $request = new Request('GET', sprintf('boards/%s/', $boardId));

        return $this->fetchBoard($request);
    }

    /**
     * Update a board.
     *
     * @param Board $board The updated board.
     *
     * @return Response The response
     */
    public function updateBoard(Board $board)
    {
        $params = array();

        if (empty($board->id)) {
            throw new InvalidArgumentException('The board id is required.');
        }

        if (!empty($board->name)) {
            $params['name'] = (string) $board->name;
        }

        if (!empty($board->description)) {
            $params['description'] = (string) $board->description;
        }

        $request = new Request('PATCH', sprintf('boards/%s/', $board->id), $params);

        return $this->fetchBoard($request);
    }

    /**
     * Get the boards of the authenticated user.
     *
     * @return Response The response
     */
    public function getUserBoards()
    {
        $request = new Request('GET', 'me/boards/');

        return $this->fetchMultipleBoards($request);
    }

    /**
     * Get the pins of the authenticated user.
     *
     * @return Response The response
     */
    public function getUserPins()
    {
        $request = new Request('GET', 'me/pins/');

        return $this->fetchMultiplePins($request);
    }

    /**
     * Get the authenticated user.
     *
     * @return Response The response
     */
    public function getCurrentUser()
    {
        $request = new Request('GET', 'me/');

        return $this->fetchUser($request);
    }

    /**
     * Get the followers of the authenticated user.
     *
     * @return Response The response
     */
    public function getUserFollowers()
    {
        $request = new Request('GET', 'me/followers/');

        return $this->fetchMultipleUsers($request);
    }

    /**
     * Get the boards that the authenticated user follows.
     *
     * @return Response The response
     */
    public function getUserFollowingBoards()
    {
        $request = new Request('GET', 'me/following/boards/');

        return $this->fetchMultipleBoards($request);
    }

    /**
     * Get the users that the authenticated user follows.
     *
     * @return Response The response
     */
    public function getUserFollowing()
    {
        $request = new Request('GET', 'me/following/users/');

        return $this->fetchMultipleUsers($request);
    }

    /**
     * Get the interests that the authenticated user follows.
     *
     * @link https://www.pinterest.com/explore/901179409185
     *
     * @return Response The response
     */
    public function getUserInterests()
    {
        $request = new Request('GET', 'me/following/interests/');

        return $this->fetchMultipleBoards($request, array('id', 'name'));
    }

    /**
     * Follow a user.
     *
     * @param string $username The username of the user to follow.
     *
     * @return Response The response
     */
    public function followUser($username)
    {
        if (empty($username)) {
            throw new InvalidArgumentException('Username is required.');
        }

        $request = new Request(
            'POST',
            'me/following/users/',
            array(
                'user' => (string) $username,
            )
        );

        return $this->execute($request);
    }

    /**
     * Create a board.
     *
     * @param string $name        The board name.
     * @param string $description The board description.
     *
     * @return Response The response
     */
    public function createBoard($name, $description = null)
    {
        if (empty($name)) {
            throw new InvalidArgumentException('The name should not be empty.');
        }

        $params = array(
            'name' => (string) $name,
        );

        if (!empty($description)) {
            $params['description'] = (string) $description;
        }

        $request = new Request('POST', 'boards/', $params);

        return $this->fetchBoard($request);
    }

    /**
     * Delete a board.
     *
     * @param int $boardId The board id.
     *
     * @return Response The response
     */
    public function deleteBoard($boardId)
    {
        if (empty($boardId)) {
            throw new InvalidArgumentException('The board id should not be empty.');
        }

        $request = new Request('DELETE', sprintf('boards/%d/', $boardId));

        return $this->execute($request);
    }

    /**
     * Create a pin on a board.
     *
     * @param string      $boardId The board id.
     * @param string      $note    The note.
     * @param Image       $image   The image.
     * @param string|null $link    The link (Optional).
     *
     * @return Response The response
     */
    public function createPin($boardId, $note, Image $image, $link = null)
    {
        if (empty($boardId)) {
            throw new InvalidArgumentException('The board id should not be empty.');
        }

        if (empty($note)) {
            throw new InvalidArgumentException('The note should not be empty.');
        }

        //Pinterest boardId is generated so long integer which crosses integer limit. (example boardId=314196648911734959).
        //After converting 314196648911734959(boardId) into integer, it becomes '2147483647' which is limit of integer datatype.
        //So the api throws "Board not found." error. To fix this need to remove typecasting.
        $params = array(
            'board' => $boardId, //(int) $boardId,    //removed typecasting here.
            'note' => (string) $note,
        );

        if (!empty($link)) {
            $params['link'] = (string) $link;
        }

        $imageKey = $image->isUrl() ? 'image_url' : ($image->isBase64() ? 'image_base64' : 'image');

        if ($image->isFile()) {
            $params[$imageKey] = $image;
        } else {
            $params[$imageKey] = $image->getData();
        }

        $request = new Request('POST', 'pins/', $params);

        return $this->fetchPin($request);
    }

    /**
     * Delete a Pin.
     *
     * @param string $pinId The id of the pin to delete.
     *
     * @return Response The response
     */
    public function deletePin($pinId)
    {
        if (empty($pinId)) {
            throw new InvalidArgumentException('The pin id should not be empty.');
        }

        $request = new Request('DELETE', sprintf('pins/%d/', $pinId));

        return $this->execute($request);
    }

    /**
     * Get the next items for a paged list.
     *
     * @param PagedList $pagedList
     *
     * @return Response The response
     */
    public function getNextItems(PagedList $pagedList)
    {
        if (!$pagedList->hasNext()) {
            throw new InvalidArgumentException('The list has no more items');
        }

        $items = $pagedList->items();

        if (empty($items)) {
            throw new InvalidArgumentException(
                'Unable to detect object type because the list contains no items'
            );
        }

        $item = reset($items);
        $objectClassName = get_class($item);
        $objectInstance = new $objectClassName();

        $request = $this->buildRequestForPagedList($pagedList);

        return $this->execute($request, function (Response $response) use ($objectInstance) {
            $mapper = new Mapper($objectInstance);

            return $mapper->toList($response);
        });
    }

    /**
     * Build a request to get the next items of a paged list.
     *
     * @param PagedList $pagedList
     *
     * @return Request
     */
    private function buildRequestForPagedList(PagedList $pagedList)
    {
        $nextItemsUri = $pagedList->getNextUrl();

        $params = array();
        $components = parse_url($nextItemsUri);
        parse_str($components['query'], $params);

        $path = $components['path'];
        $versionPath = sprintf('/%s/', Authentication::API_VERSION);
        $versionPathLength = strlen($versionPath);
        $path = substr($path, $versionPathLength);

        return new Request('GET', $path, $params);
    }

    /**
     * Get the pins of a board.
     *
     * @param string $boardId
     *
     * @return Response The response
     */
    public function getBoardPins($boardId)
    {
        if (empty($boardId)) {
            throw new InvalidArgumentException('The board id should not be empty.');
        }

        $endpoint = sprintf('boards/%s/pins/', $boardId);
        $request = new Request('GET', $endpoint);

        return $this->fetchMultiplePins($request);
    }
}
