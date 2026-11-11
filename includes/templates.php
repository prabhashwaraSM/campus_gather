<?php
declare(strict_types=1);

/**
 * Standard organizing sections and templates for University of Moratuwa Faculty of IT events.
 */
function standard_section_templates(): array {
    return [
        'Finance' => [
            'name' => 'Finance',
            'icon' => '💳',
            'description' => 'Plans the event budget, tracks expenditures, reimburses verified receipts, and balances accounts.',
            'responsibilities' => "Prepare detailed budget breakdowns;\nRecord venue and printing expenses;\nCollect and audit vendor receipts;\nManage cash float on event day;\nCompile post-event financial audit report.",
            'useful_skills' => 'Excel / Google Sheets, accounting basics, budget accuracy. Beginners welcome!',
            'member_limit' => 5,
            'default_tasks' => [
                'Prepare provisional event budget spreadsheet',
                'Verify vendor quotations for banners and audio equipment',
                'Collect and reconcile receipts for Dean office audit'
            ]
        ],
        'Design' => [
            'name' => 'Design',
            'icon' => '🎨',
            'description' => 'Creates the event visual brand identity, social media campaign graphics, badges, and certificates.',
            'responsibilities' => "Design keynote posters and social media banners;\nCreate Instagram & LinkedIn teaser carousels;\nDesign stage backdrop and projector holding screens;\nDesign participant ID cards and winner certificates.",
            'useful_skills' => 'Figma, Adobe Photoshop/Illustrator, Canva, color theory. Creative beginners welcome!',
            'member_limit' => 6,
            'default_tasks' => [
                'Finalize event branding colors, typography and logo mark',
                'Design social media announcement poster series',
                'Create digital participation certificates and ID badge templates'
            ]
        ],
        'Publicity & Media' => [
            'name' => 'Publicity & Media',
            'icon' => '📣',
            'description' => 'Drives campus-wide promotion, writes engaging announcements, produces video reels, and handles photography.',
            'responsibilities' => "Draft captions and publish across social media;\nCoordinate batch WhatsApp announcements;\nShoot promotional teaser video reels;\nDocument live event photography and videography.",
            'useful_skills' => 'Copywriting, photography, video editing, social media management. Beginners welcome!',
            'member_limit' => 8,
            'default_tasks' => [
                'Draft and distribute announcement copy for student batch groups',
                'Coordinate with university media units for live coverage',
                'Record and edit 30-second teaser reel for Instagram/TikTok'
            ]
        ],
        'Logistics' => [
            'name' => 'Logistics',
            'icon' => '📦',
            'description' => 'Oversees venue arrangements, audio-visual equipment, table setup, power supplies, and banners.',
            'responsibilities' => "Reserve faculty lecture halls, labs, and auditorium;\nArrange participant desks, chairs, and stage layout;\nCoordinate power extension boards and audio cables;\nEnsure clean venue turnover after event.",
            'useful_skills' => 'Physical coordination, floor arrangement, punctual scheduling.',
            'member_limit' => 10,
            'default_tasks' => [
                'Submit hall and lab reservation letter to Dean\'s office',
                'Inspect 40 power extension boards and cabling in lab complex',
                'Coordinate stage backdrop and standee banner placement'
            ]
        ],
        'Technical' => [
            'name' => 'Technical',
            'icon' => '💻',
            'description' => 'Maintains the hackathon submission portal, Wi-Fi connectivity, dual-screen projectors, and live scoring.',
            'responsibilities' => "Configure high-speed Wi-Fi network with Network Center;\nDeploy and test automated judging portal and GitHub triggers;\nManage audio-visual switchers and projector inputs during talks;\nProvide on-site technical support to participants.",
            'useful_skills' => 'Networking, system admin, GitHub, AV equipment troubleshooting.',
            'member_limit' => 6,
            'default_tasks' => [
                'Liaise with Network Operations Center for dedicated Wi-Fi bandwidth',
                'Deploy, test, and monitor code submission server / judging portal',
                'Test auditorium dual-projector setup and presenter remotes'
            ]
        ],
        'Programme' => [
            'name' => 'Programme',
            'icon' => '📋',
            'description' => 'Curates the schedule, drafts minute-by-minute rundown, and coordinates keynote speakers and judges.',
            'responsibilities' => "Plan the full event itinerary and minute-by-minute rundown;\nCoordinate speaker invitations, arrival, and briefing;\nRehearse with Master of Ceremonies (MC);\nCoordinate judging panel scoring rounds.",
            'useful_skills' => 'Agenda planning, public speaking, diplomatic coordination.',
            'member_limit' => 5,
            'default_tasks' => [
                'Draft minute-by-minute schedule and stage rundown',
                'Confirm keynote guest speaker and industry judges',
                'Brief Master of Ceremonies (MC) for opening and closing ceremonies'
            ]
        ],
        'Sponsorship' => [
            'name' => 'Sponsorship',
            'icon' => '🤝',
            'description' => 'Reaches out to corporate tech sponsors, tracks deliverables, and coordinates sponsor booths.',
            'responsibilities' => "Prepare corporate sponsorship proposal deck;\nReach out to IT companies and follow up via email/phone;\nVerify sponsor logo placements on all promotional items;\nHost sponsor delegates on event day.",
            'useful_skills' => 'Professional email writing, negotiation, interpersonal communication.',
            'member_limit' => 5,
            'default_tasks' => [
                'Finalize corporate sponsorship proposal deck PDF',
                'Send out sponsorship requests to software industry partners',
                'Collect vector logos and swag items for participant bags'
            ]
        ],
        'Registration & Hospitality' => [
            'name' => 'Registration & Hospitality',
            'icon' => '🎟️',
            'description' => 'Welcomes participants, manages badge check-in, distributes swags, and coordinates meals and refreshments.',
            'responsibilities' => "Build online check-in list and attendance registry;\nIssue participant wristbands and welcome kits;\nCoordinate catering orders, tea breaks, and meal distribution;\nAssist VIP guests and faculty members.",
            'useful_skills' => 'Warm hospitality, crowd handling, punctuality. Beginners welcome!',
            'member_limit' => 8,
            'default_tasks' => [
                'Set up digital check-in spreadsheet / QR code system',
                'Finalize meal and refreshment catering orders for participants',
                'Assemble participant welcome bags with badges and stickers'
            ]
        ]
    ];
}
