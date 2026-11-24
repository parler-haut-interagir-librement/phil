<?php

declare(strict_types=1);

use Phil\FaqBundle\Infrastructure\Http\FaqAdminController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The administration of the questions, mounted by the application under a prefix of its choice, behind its firewall:
 *
 *     # config/routes/phil_faq.yaml
 *     phil_faq_admin:
 *         resource: '@PhilFaqBundle/config/admin_routes.php'
 *         prefix: /admin/faq
 */
return static function (RoutingConfigurator $routes): void {
    $id = ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'];
    $controller = FaqAdminController::class;

    $routes->add(FaqAdminController::INDEX_ROUTE, '/')->controller([$controller, 'index'])->methods(['GET']);
    $routes->add('phil_faq_admin_section_new', '/sections/new')->controller([$controller, 'newSection'])->methods(['GET', 'POST']);
    $routes->add('phil_faq_admin_section_edit', '/sections/{id}/edit')->controller([$controller, 'editSection'])->requirements($id)->methods(['GET', 'POST']);
    $routes->add('phil_faq_admin_section_move', '/sections/{id}/move')->controller([$controller, 'moveSection'])->requirements($id)->methods(['POST']);
    $routes->add('phil_faq_admin_section_delete', '/sections/{id}/delete')->controller([$controller, 'deleteSection'])->requirements($id)->methods(['POST']);
    $routes->add('phil_faq_admin_question_new', '/sections/{section}/questions/new')->controller([$controller, 'newQuestion'])->requirements(['section' => $id['id']])->methods(['GET', 'POST']);
    $routes->add('phil_faq_admin_question_edit', '/questions/{id}/edit')->controller([$controller, 'editQuestion'])->requirements($id)->methods(['GET', 'POST']);
    $routes->add('phil_faq_admin_question_move', '/questions/{id}/move')->controller([$controller, 'moveQuestion'])->requirements($id)->methods(['POST']);
    $routes->add('phil_faq_admin_question_publish', '/questions/{id}/publish')->controller([$controller, 'publishQuestion'])->requirements($id)->methods(['POST']);
    $routes->add('phil_faq_admin_question_delete', '/questions/{id}/delete')->controller([$controller, 'deleteQuestion'])->requirements($id)->methods(['POST']);
    $routes->add(FaqAdminController::STATISTICS_ROUTE, '/statistics')->controller([$controller, 'statistics'])->methods(['GET']);
    $routes->add('phil_faq_admin_unanswered_forget', '/statistics/forget')->controller([$controller, 'forgetSearch'])->methods(['POST']);
};
