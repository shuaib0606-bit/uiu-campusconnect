# Asset manifest — CampusConnect

Logos, covers and banners added. The updater `AssetPathsSeeder` only fills empty slots, so **no real photo already uploaded is ever replaced**. People are untouched.

Apply on your Mac:

```
php artisan storage:link      # once, if not already linked
php artisan db:seed --class=AssetPathsSeeder
```

## Clubs (16)

| Club | Logo | Cover | Source |
|---|---|---|---|
| UIU Computer Club | `clubs/logos/uiu-computer-club.jpg` | `clubs/uiu-computer-club.jpg` | Generated — swap in real FB art |
| UIU Robotics Club | `clubs/logos/uiu-robotics-club.jpg` | `clubs/uiu-robotics-club.jpg` | Generated — swap in real FB art |
| UIU App Forum | `clubs/logos/uiu-app-forum.jpg` | `clubs/uiu-app-forum.jpg` | **Real** (already on file) |
| UIU Electrical & Electronics Club | `clubs/logos/uiu-electrical-electronics-club.jpg` | `clubs/uiu-electrical-electronics-club.jpg` | Generated — swap in real FB art |
| UIU Business Club | `clubs/logos/uiu-business-club.jpg` | `clubs/uiu-business-club.jpg` | **Real** (already on file) |
| UIU Marketing Forum | `clubs/logos/uiu-marketing-forum.jpg` | `clubs/uiu-marketing-forum.jpg` | Generated — swap in real FB art |
| UIU Entrepreneur Development Forum | `clubs/logos/uiu-entrepreneur-development-forum.jpg` | `clubs/uiu-entrepreneur-development-forum.jpg` | Generated — swap in real FB art |
| UIU Debate Club | `clubs/logos/uiu-debate-club.jpg` | `clubs/uiu-debate-club.png` | **Real** (already on file) |
| UIU Cultural Club | `clubs/logos/uiu-cultural-club.jpg` | `clubs/uiu-cultural-club.jpg` | **Real** (already on file) |
| UIU Photography Club | `clubs/logos/uiu-photography-club.jpg` | `clubs/uiu-photography-club.png` | **Real** (already on file) |
| UIU Theater and Film Club | `clubs/logos/uiu-theater-and-film-club.jpg` | `clubs/uiu-theater-and-film-club.jpg` | Generated — swap in real FB art |
| UIU Sports Club | `clubs/logos/uiu-sports-club.jpg` | `clubs/uiu-sports-club.jpg` | **Real** (already on file) |
| UIU Social Service Club | `clubs/logos/uiu-social-service-club.jpg` | `clubs/uiu-social-service-club.jpg` | **Real** (already on file) |
| UIU Science Forum | `clubs/logos/uiu-science-forum.jpg` | `clubs/uiu-science-forum.jpg` | Generated — swap in real FB art |
| UIU English Language Forum | `clubs/logos/uiu-english-language-forum.jpg` | `clubs/uiu-english-language-forum.jpg` | Generated — swap in real FB art |
| Model United Nations Club | `clubs/logos/model-united-nations-club.jpg` | `clubs/model-united-nations-club.jpg` | Generated — swap in real FB art |

## Events (10)

| Item | File | Source |
|---|---|---|
| UIU Intra-University Programming Contest | `events/uiu-intra-university-programming-contest.jpg` | Generated card |
| Hack the Campus 2026 | `events/hack-the-campus-2026.jpg` | Generated card |
| Robotics Workshop: Line Following Robots | `events/robotics-workshop-line-following-robots.jpg` | Generated card |
| CV Writing and Interview Skills | `events/cv-writing-and-interview-skills.jpg` | Generated card |
| UIU Cultural Night 2026 | `events/uiu-cultural-night-2026.jpg` | Generated card |
| Inter-Department Football Tournament | `events/inter-department-football-tournament.jpg` | Generated card |
| Photo Walk: Old Dhaka | `events/photo-walk-old-dhaka.jpg` | Generated card |
| Blood Donation Camp | `events/blood-donation-camp.jpg` | Generated card |
| Startup Pitch Night | `events/startup-pitch-night.jpg` | Generated card |
| MUN Delegate Training Session | `events/mun-delegate-training-session.jpg` | Generated card |

## Marketplace (11)

| Item | File | Source |
|---|---|---|
| Data Structures & Algorithms in C++ (3rd ed.) | `marketplace/data-structures-algorithms-in-c-3rd-ed.jpg` | Generated card |
| Casio fx-991EX Classwiz | `marketplace/casio-fx-991ex-classwiz.jpg` | Generated card |
| HP Pavilion 14 - i5 11th gen, 8GB, 512GB SSD | `marketplace/hp-pavilion-14-i5-11th-gen-8gb-512gb-ssd.jpg` | Generated card |
| Engineering Mechanics - Hibbeler | `marketplace/engineering-mechanics-hibbeler.jpg` | Generated card |
| Roommate wanted - Bashundhara R/A | `marketplace/roommate-wanted-bashundhara-r-a.jpg` | Generated card |
| Physics tuition - HSC level | `marketplace/physics-tuition-hsc-level.jpg` | Generated card |
| Bicycle - Duranta single speed | `marketplace/bicycle-duranta-single-speed.jpg` | Generated card |
| Scientific drawing set + drafting board | `marketplace/scientific-drawing-set-drafting-board.jpg` | Generated card |
| Principles of Marketing - Kotler & Armstrong | `marketplace/principles-of-marketing-kotler-armstrong.jpg` | Generated card |
| Mechanical keyboard - Redragon Kumara | `marketplace/mechanical-keyboard-redragon-kumara.jpg` | Generated card |
| Lab coat (size M) - Pharmacy | `marketplace/lab-coat-size-m-pharmacy.jpg` | Generated card |

## Lost & Found (8)

| Item | File | Source |
|---|---|---|
| Black Casio fx-991EX with name sticker (lost) | `lost-found/black-casio-fx-991ex-with-name-sticker.jpg` | Generated card |
| UIU ID card - CSE student (found) | `lost-found/uiu-id-card-cse-student.jpg` | Generated card |
| Blue Jansport backpack (lost) | `lost-found/blue-jansport-backpack.jpg` | Generated card |
| Set of keys with a red keychain (found) | `lost-found/set-of-keys-with-a-red-keychain.jpg` | Generated card |
| Prescription glasses in a black case (lost) | `lost-found/prescription-glasses-in-a-black-case.jpg` | Generated card |
| Wireless earbuds case (white) (found) | `lost-found/wireless-earbuds-case-white.jpg` | Generated card |
| Lab notebook - Electrical Machines (lost) | `lost-found/lab-notebook-electrical-machines.jpg` | Generated card |
| Grey hoodie, size L (found) | `lost-found/grey-hoodie-size-l.jpg` | Generated card |

## To swap in real club logos & covers

Save each club's Facebook **profile photo** and **cover** into one folder, named by the slug above:

- cover → `<slug>.jpg` (e.g. `uiu-robotics-club.jpg`)
- logo → `<slug>-logo.jpg` (e.g. `uiu-robotics-club-logo.jpg`)

Send the folder and I'll crop, optimise and drop them in at the same paths — the manifest and seeder won't need to change.
