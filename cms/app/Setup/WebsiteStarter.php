<?php

declare(strict_types=1);

namespace CometCMS\Setup;

use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Site\SiteRepository;
use CometCMS\Workspaces\WorkspaceContext;

/** Initial website content; everything remains editable through the CMS. */
final class WebsiteStarter
{
    public function __construct(private readonly WorkspaceContext $workspace) {}

    public function create(array $user, bool $includeEvents = true): void
    {
        $types = new ContentTypeRepository($this->workspace);
        $fields = [
            'title' => ['type' => 'text', 'label' => 'Title', 'required' => true],
            'slug' => ['type' => 'slug', 'label' => 'Slug', 'required' => true, 'unique' => true],
            'intro' => ['type' => 'textarea', 'label' => 'Introduction'],
            'body' => ['type' => 'markdown', 'label' => 'Page content'],
        ];
        $types->save([
            'name' => 'landing-page',
            'label' => 'Landing Page',
            'icon' => 'mdi:home-outline',
            'singleton' => true,
            'fields' => $fields,
        ]);
        $types->save([
            'name' => 'pages',
            'label' => 'Pages',
            'icon' => 'mdi:file-document-multiple-outline',
            'fields' => $fields,
        ]);
        if ($includeEvents) {
            $types->save([
                'name' => 'events',
                'label' => 'Events',
                'icon' => 'mdi:calendar-outline',
                'fields' => $fields + [
                    'starts_at' => ['type' => 'datetime', 'label' => 'Starts at', 'required' => true],
                    'location' => ['type' => 'text', 'label' => 'Location', 'required' => true],
                    'event_type' => ['type' => 'select', 'label' => 'Event type', 'required' => true, 'options' => [
                        'meetup' => 'Meetup',
                        'workshop' => 'Workshop',
                        'conference' => 'Conference',
                    ]],
                    'capacity' => ['type' => 'number', 'label' => 'Capacity'],
                    'registration_open' => ['type' => 'boolean', 'label' => 'Registration open'],
                ],
            ]);
        }

        $content = ContentRepository::make($this->workspace);
        $home = $content->save('landing-page', [
            'title' => 'Welcome to your website',
            'intro' => 'A fresh start for your next idea.',
            'body' => "## Make yourself at home\n\nTell your story, share what you do, and give visitors a reason to explore. This is your space to make it happen.",
            'status' => 'published',
        ], $user);
        $about = $content->save('pages', [
            'title' => 'About',
            'slug' => 'about',
            'intro' => 'Every great idea has a story.',
            'body' => "## Our story\n\nIntroduce yourself or your team here. Share what you care about, what you do, and what makes your work special.",
            'status' => 'published',
        ], $user);
        $hello = $content->save('pages', [
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'intro' => 'Something new starts here.',
            'body' => "## Hello, world!\n\nWelcome to the first of many stories. There is plenty more to come, and we are glad you are here for the beginning.",
            'status' => 'published',
        ], $user);
        if ($includeEvents) {
            $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
            $content->save('events', [
                'title' => 'Community Meetup',
                'slug' => 'community-meetup',
                'intro' => 'Meet the people behind the ideas.',
                'body' => "## Come say hello\n\nJoin us for an evening of friendly conversations, fresh ideas, and new connections. Everyone is welcome, whether you are a familiar face or visiting for the first time.",
                'starts_at' => $today->modify('+14 days')->setTime(18, 0)->format(DATE_ATOM),
                'location' => 'Community Hub',
                'event_type' => 'meetup',
                'capacity' => 50,
                'registration_open' => true,
                'status' => 'published',
            ], $user);
            $content->save('events', [
                'title' => 'Creative Workshop',
                'slug' => 'creative-workshop',
                'intro' => 'Make something new, together.',
                'body' => "## An afternoon of making\n\nBring your curiosity and explore new creative techniques with a small group. Materials are provided. This session is fully booked, so registration is now closed.",
                'starts_at' => $today->modify('+21 days')->setTime(14, 0)->format(DATE_ATOM),
                'location' => 'Studio Space',
                'event_type' => 'workshop',
                'capacity' => 16,
                'registration_open' => false,
                'status' => 'published',
            ], $user);
        }

        $site = new SiteRepository($this->workspace, $types);
        $site->saveSettings([
            'homepage' => ['type' => 'landing-page', 'entry' => $home['id']],
            'routes' => [
                'landing-page' => ['enabled' => true, 'seo_description_field' => 'intro'],
                'pages' => ['enabled' => true, 'seo_description_field' => 'intro'],
                ...($includeEvents ? ['events' => ['enabled' => true, 'seo_description_field' => 'intro']] : []),
            ],
        ]);
        $site->saveMenus([
            ['name' => 'main', 'label' => 'Main', 'items' => [
                ['id' => 'home', 'label' => 'Home', 'link' => ['type' => 'home']],
                ['id' => 'about', 'label' => 'About', 'link' => ['type' => 'entry', 'collection' => 'pages', 'entry' => $about['id']]],
                ['id' => 'hello-world', 'label' => 'Hello World', 'link' => ['type' => 'entry', 'collection' => 'pages', 'entry' => $hello['id']]],
                ...($includeEvents ? [['id' => 'events', 'label' => 'Events', 'link' => ['type' => 'index', 'collection' => 'events']]] : []),
            ]],
            ['name' => 'footer', 'label' => 'Footer', 'items' => []],
        ]);
    }
}
