<?php

declare(strict_types=1);

namespace Mediadreams\MdNewsfrontend\Event;

/**
 *
 * This file is part of the "News frontend" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2021 Christoph Daecke <typo3@mediadreams.org>
 *
 */

use Mediadreams\MdNewsfrontend\Controller\NewsController;
use Mediadreams\MdNewsfrontend\Domain\Model\News;

/**
 * Class BaseEvent
 * @package Mediadreams\MdNewsfrontend\Event
 */
abstract class BaseEvent
{
    /**
     * @var News
     */
    private News $news;

    /**
     * @var NewsController
     */
    private $newsController;

    /**
     * BaseEvent constructor.
     *
     * @param News $news
     * @param NewsController $newsController
     */
    public function __construct(News $news, NewsController $newsController)
    {
        $this->news = $news;
        $this->newsController = $newsController;
    }

    /**
     * @return News
     */
    public function getNews(): News
    {
        return $this->news;
    }

    /**
     * @param News $news
     */
    public function setNews(News $news): void
    {
        $this->news = $news;
    }

    /**
     * @return NewsController
     */
    public function getNewsController(): NewsController
    {
        return $this->newsController;
    }
}
