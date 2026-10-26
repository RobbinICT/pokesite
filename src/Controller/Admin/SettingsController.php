<?php

namespace App\Controller\Admin;

use App\Entity\Config;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Site settings (the config row), toggled from the admin.
 * Routes have a priority so they win over the catch-all /admin/{type}.
 */
class SettingsController extends AbstractController
{
    private const CSRF_ID = 'admin-settings';

    private EntityManagerInterface $entity_manager;

    public function __construct(EntityManagerInterface $entity_manager)
    {
        $this->entity_manager = $entity_manager;
    }

    #[Route(path: '/admin/settings', name: 'admin_settings', methods: ['GET'], priority: 10)]
    public function index(): Response
    {
        $config = $this->entity_manager->getRepository(Config::class)->getOrCreateConfig();

        $settings = [];
        foreach (self::getSettings() as $key => $setting)
        {
            $settings[] = $setting + ['key' => $key, 'value' => $setting['get']($config)];
        }

        return $this->render('admin/settings.html.twig', [
            'settings' => $settings,
            'csrf_id' => self::CSRF_ID,
            'active_key' => 'settings',
        ]);
    }

    #[Route(path: '/admin/settings/{key}', name: 'admin_settings_update', requirements: ['key' => '[a-z_]+'], methods: ['POST'], priority: 10)]
    public function update(Request $request, string $key): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, $request->headers->get('X-CSRF-Token', '')))
        {
            return new JsonResponse(['error' => 'Your session expired, please reload the page.'], Response::HTTP_FORBIDDEN);
        }

        $setting = self::getSettings()[$key] ?? null;
        $value = json_decode($request->getContent(), true)['value'] ?? null;
        if ($setting === null)
        {
            return new JsonResponse(['error' => 'Unknown setting.'], Response::HTTP_NOT_FOUND);
        }
        if (!\is_bool($value))
        {
            return new JsonResponse(['error' => 'Value must be true or false.'], Response::HTTP_BAD_REQUEST);
        }

        $config = $this->entity_manager->getRepository(Config::class)->getOrCreateConfig();
        $setting['set']($config, $value);
        $this->entity_manager->flush();

        return new JsonResponse([
            'key' => $key,
            'value' => $setting['get']($config),
            'message' => sprintf('%s turned %s.', $setting['label'], $value ? 'on' : 'off'),
        ]);
    }

    /**
     * Every editable setting, keyed by its URL name.
     * @return array<string, array{label: string, description: string, get: callable, set: callable}>
     */
    private static function getSettings(): array
    {
        return [
            'local_cards' => [
                'label' => 'Local card images',
                'description' => 'Show Pokémon card images from the server\'s images folder instead of the original image URLs.',
                'get' => fn (Config $config) => $config->getUseLocalCards(),
                'set' => fn (Config $config, bool $value) => $config->setUseLocalCards($value),
            ],
            'local_minifigures' => [
                'label' => 'Local minifigure images',
                'description' => 'Show LEGO minifigure images from the server\'s images folder instead of the original image URLs.',
                'get' => fn (Config $config) => $config->getUseLocalMinifigures(),
                'set' => fn (Config $config, bool $value) => $config->setUseLocalMinifigures($value),
            ],
        ];
    }
}
