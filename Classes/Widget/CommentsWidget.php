<?php

declare(strict_types=1);

namespace B13\AnnotateWidget\Widget;

/*
 * This file is part of TYPO3 CMS-based extension "annotate" by b13.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use Doctrine\DBAL\ParameterType;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;

class CommentsWidget implements WidgetInterface, RequestAwareWidgetInterface
{
    private ServerRequestInterface $request;

    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly ViewFactoryInterface $viewFactory,
        private readonly ConnectionPool $connectionPool
    ) {}

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function renderWidgetContent(): string
    {
        $view = $this->viewFactory->create(new ViewFactoryData(
            layoutRootPaths: ['EXT:dashboard/Resources/Private/Layouts/'],
            templateRootPaths: ['EXT:annotate/Resources/Private/Templates'],
            request: $this->request
        ));
        $view->assignMultiple([
            'pages' => $this->getPagesWithComments(),
            'configuration' => $this->configuration,
        ]);
        return $view->render('Widget/Comments');
    }

    protected function getPagesWithComments(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        return $queryBuilder
            ->selectLiteral('COUNT(sys_comment.uid) AS commentCount')
            ->addSelect('pages.uid', 'pages.title')
            ->from('pages')
            ->innerJoin(
                'pages',
                'sys_comment',
                'sys_comment',
                $queryBuilder->expr()->eq('sys_comment.pid', 'pages.uid')
            )
            ->where(
                $queryBuilder->expr()->eq('sys_comment.isresolved', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_comment.parentcomment', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
            )
            ->groupBy(
                'pages.uid', 'pages.title'
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function getOptions(): array
    {
        return [];
    }
}