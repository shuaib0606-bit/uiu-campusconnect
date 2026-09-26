<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\Links;
use App\Services\Ai\Scopes;
use App\Support\Visibility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Who works on computer networks?" - people by subject, not by name.
 *
 * The people tool matches names and departments, so a question about a
 * research area used to find nobody, and the model filled the gap by
 * guessing. This reads what faculty actually wrote about their work: the
 * research interests on their profile, the sections of their Academic tab
 * (Research Interests, Courses, Publications...), the courses they list and
 * their skills. Every match comes back with the line it was found in, so an
 * answer can say *why* someone is listed.
 *
 * Privacy is the same as the profile page: a field or section someone keeps
 * to themselves (or hides from others) is never searched for anyone but
 * them, and blocked or inactive accounts are not searched at all.
 */
class ExpertiseTool extends Tool
{
    private const MAX_PEOPLE = 20;

    private const EVIDENCE_PER_PERSON = 3;

    /**
     * What people say for a subject, and what faculty write for it.
     * Keys are the normalised topic; values are the phrases searched,
     * strongest first. A "~" marks a loose phrase ("quantum" for quantum
     * computing): it still counts, but far less than the real thing.
     */
    private const ALIASES = [
        'ai' => ['artificial intelligence', 'ai', 'intelligent systems', 'human-centered ai', 'generative ai'],
        'artificial intelligence' => ['artificial intelligence', 'ai', 'intelligent systems'],
        'ml' => ['machine learning', 'ml', 'deep learning', 'neural network', 'federated learning', 'reinforcement learning'],
        'machine learning' => ['machine learning', 'ml', 'deep learning', 'neural network', 'federated learning', 'reinforcement learning'],
        'deep learning' => ['deep learning', 'neural network', 'deep neural'],
        'nlp' => ['natural language processing', 'nlp', 'computational linguistics', 'language technology', 'text mining'],
        'natural language processing' => ['natural language processing', 'nlp', 'computational linguistics', 'language technology'],
        'computer vision' => ['computer vision', 'image processing', 'pattern recognition', 'object detection'],
        'networks' => ['computer network', 'wireless network', 'sensor network', 'network security', 'vehicular network', 'smart network', 'sdn', '5g', '6g', 'future internet', '~networking', '~routing'],
        'computer networks' => ['computer network', 'wireless network', 'sensor network', 'network security', 'vehicular network', 'smart network', 'sdn', '5g', '6g', 'future internet', '~networking', '~routing'],
        'networking' => ['computer network', 'wireless network', 'sensor network', 'network security', 'sdn', '~networking', '~routing'],
        'security' => ['cybersecurity', 'cyber security', 'network security', 'information security', 'cryptography', '~security', '~privacy'],
        'cyber security' => ['cybersecurity', 'cyber security', 'network security', 'information security', 'cryptography'],
        'cybersecurity' => ['cybersecurity', 'cyber security', 'network security', 'information security', 'cryptography'],
        'quantum' => ['quantum computing', 'quantum machine learning', 'quantum information', 'quantum communication', '~quantum'],
        'quantum computing' => ['quantum computing', 'quantum machine learning', 'quantum information', 'quantum communication', 'quantum algorithm', '~quantum'],
        'data science' => ['data science', 'data mining', 'data analytics', 'big data', 'data engineering'],
        'iot' => ['internet of things', 'iot'],
        'internet of things' => ['internet of things', 'iot'],
        'hci' => ['human computer interaction', 'human-computer interaction', 'hci', 'user experience', 'cscw'],
        'blockchain' => ['blockchain', 'distributed ledger'],
        'robotics' => ['robotics', '~robot'],
        'cloud' => ['cloud computing', 'edge computing', 'fog computing'],
        'cloud computing' => ['cloud computing', 'edge computing', 'fog computing'],
        'bioinformatics' => ['bioinformatics', 'computational biology', 'genomics', 'rna-seq'],
        'software engineering' => ['software engineering', 'software testing', 'software project management'],
    ];

    /** Words a question wraps a subject in. */
    private const FILLER = '/\b(what|which|any|anyone|someone|is|are|does|do|doing|there|interested|research(ing|er|ers)?|interest(s|ed)?|in|on|about|working|work(s)?|faculty|faculties|teachers?|professors?|who|domains?|fields?|areas?|topics?|subjects?|the|of|related|expertise|experts?)\b/i';

    public function name(): string
    {
        return 'find_expertise';
    }

    public function description(): string
    {
        return 'Find faculty (or others) by subject: who researches, teaches or works on a topic such as "computer networks", "machine learning", "quantum computing". Returns each person with the lines from their profile that mention it.';
    }

    public function parameters(): array
    {
        return self::object([
            'topic' => self::text('The subject only, e.g. "computer networks" or "AI, machine learning". Several subjects may be separated by commas.'),
            'department' => self::text('Optional department to narrow to, e.g. "CSE" or "Business".'),
            'role' => self::choice(['faculty', 'any'], 'faculty (default) for teachers and staff; any to include students\' skills.'),
        ], ['topic']);
    }

    public function run(array $arguments): array
    {
        $topics = $this->topics($this->arg($arguments, 'topic'));

        if ($topics === []) {
            return ['error' => 'Name a subject, e.g. "computer networks".'];
        }

        $patterns = [];

        foreach ($topics as $topic) {
            foreach (self::ALIASES[$topic] ?? [$topic] as $phrase) {
                $loose = str_starts_with($phrase, '~');
                $phrase = ltrim($phrase, '~');
                $patterns[$phrase] ??= ['topic' => $topic, 'factor' => $loose ? 0.25 : 1.0];
            }
        }

        $candidates = $this->people($this->arg($arguments, 'department'), $this->arg($arguments, 'role') === 'any');

        if ($candidates->isEmpty()) {
            return ['people' => [], 'note' => 'Nobody to search in that department.'];
        }

        $hits = [];

        foreach ($this->texts($candidates->keys()->all(), array_keys($patterns)) as [$userId, $source, $weight, $text]) {
            $matched = [];

            // Each subject is looked for separately, so "AI and Machine
            // Learning" counts for both; within a subject the strongest
            // phrase that matches wins.
            foreach ($patterns as $phrase => ['topic' => $topic, 'factor' => $factor]) {
                if (isset($matched[$topic]) || ! preg_match($this->regex($phrase), $text, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                $matched[$topic] = true;
                $points = $weight * $factor;

                // A clear match is a research, teaching or bio mention; a word in
                // a publication list or an award is only a loose one.
                if ($points >= 2) {
                    $hits[$userId]['topics'][$topic] = true;
                } else {
                    $hits[$userId]['topics'] ??= [];
                }

                $hits[$userId]['score'] = ($hits[$userId]['score'] ?? 0) + $points;

                // One line of evidence per source, the strongest kept.
                if (($hits[$userId]['evidence'][$source]['weight'] ?? -1) < $points) {
                    $hits[$userId]['evidence'][$source] = ['from' => $source, 'weight' => $points, 'text' => $this->evidenceLine($text, $m[0][1])];
                }
            }
        }

        if ($hits === []) {
            return [
                'people' => [],
                'searched_for' => array_keys($patterns),
                'note' => 'No profile mentions this. Say so plainly and do not suggest anyone - nobody here has written about it.',
            ];
        }

        uasort($hits, fn ($a, $b) => [count($b['topics']), $b['score']] <=> [count($a['topics']), $a['score']]);

        // Only loose matches ("quantum" in a physics paper) is weak evidence;
        // say so rather than present it as the same thing.
        $strong = array_filter($hits, fn ($h) => $h['topics'] !== []);

        $people = [];

        foreach (array_slice($hits, 0, self::MAX_PEOPLE, true) as $userId => $hit) {
            $person = $candidates[$userId];
            $evidence = collect($hit['evidence'])->sortByDesc('weight')->take(self::EVIDENCE_PER_PERSON)
                ->map(fn ($e) => ['from' => $e['from'], 'text' => $e['text']])->values()->all();

            $people[] = array_filter([
                'name' => $person->full_name,
                'link' => Links::profile($person),
                'role' => $person->role,
                'designation' => $person->designation,
                'department' => $person->department,
                'on_leave' => stripos((string) $person->designation, 'leave') !== false ? true : null,
                'matches' => $hit['topics'] !== [] ? array_keys($hit['topics']) : null,
                'loose_match_only' => $hit['topics'] === [] ? true : null,
                'evidence' => $evidence,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return [
            'people' => $people,
            'total' => count($hits),
            'total_clear_matches' => count($strong),
            'searched_for' => array_keys($patterns),
            'note' => 'Strongest matches first. Describe each person\'s work only with their evidence lines - nothing else is known about them. People marked loose_match_only only mention it in passing (a paper title, a related word such as "quantum" in physics); list them separately as possibly related, or leave them out.'
                .(count($hits) > self::MAX_PEOPLE ? ' More people match than are listed; say so.' : ''),
        ];
    }

    /** @return list<string> normalised subjects */
    private function topics(string $raw): array
    {
        $raw = mb_strtolower(str_replace(['&', '/', ';', '|'], ',', $raw));
        $parts = preg_split('/\s*,\s*|\s+\band\b\s+|\s+\bor\b\s+/u', $raw) ?: [];
        $topics = [];

        foreach ($parts as $part) {
            $part = trim(preg_replace('/\s+/', ' ', preg_replace(self::FILLER, ' ', $part)), " .?!\"'");

            if (mb_strlen($part) >= 2) {
                $topics[$part] = true;
            }
        }

        return array_slice(array_keys($topics), 0, 6);
    }

    /**
     * A phrase as a whole-word pattern: "computer network" also finds
     * "Computer Networks" and "computer-network", but "ai" never finds
     * "maintain", and "network" is not "neural network".
     */
    private function regex(string $phrase): string
    {
        $words = array_map(fn ($w) => preg_quote($w, '/').'(?:s|es)?', preg_split('/[\s-]+/', $phrase));
        $guard = in_array($phrase, ['networking', 'computer network'], true) ? '(?<!neural )' : '';

        return '/'.$guard.'(?<![\p{L}\p{N}])'.implode('[\s-]+', $words).'(?![\p{L}\p{N}])/iu';
    }

    /** Everyone who may be searched, keyed by id. */
    private function people(string $department, bool $anyone): Collection
    {
        $department = trim(preg_replace('/\b(dept|department|of)\b\.?/i', '', $department));
        $aliases = ['cse' => 'Computer Science', 'eee' => 'Electrical', 'bba' => 'Business', 'mba' => 'Business', 'ce' => 'Civil'];
        $department = $aliases[mb_strtolower($department)] ?? $department;

        return Scopes::visiblePeople($this->viewer)
            ->select('users.id', 'users.public_id', 'users.full_name', 'users.role', 'users.designation', 'users.department')
            ->when(! $anyone, fn ($q) => $q->whereIn('users.role', ['faculty', 'official']))
            ->when($department !== '', fn ($q) => $q->where('users.department', 'like', '%'.addcslashes($department, '%_\\').'%'))
            ->get()
            ->keyBy('id');
    }

    /**
     * Every piece of text these people let the viewer see that might
     * mention a phrase, as [user id, where it is, weight, text]. The SQL
     * only narrows; the whole-word check happens in PHP.
     *
     * @return \Generator<array{0: int, 1: string, 2: int, 3: string}>
     */
    private function texts(array $userIds, array $phrases): \Generator
    {
        $allowed = Visibility::forMembers();
        $viewerId = $this->viewer->id;
        $like = function ($query, string $column) use ($phrases) {
            $query->where(function ($q) use ($column, $phrases) {
                foreach ($phrases as $phrase) {
                    foreach (preg_split('/[\s-]+/', $phrase) as $i => $word) {
                        if ($i === 0) {
                            $q->orWhere($column, 'like', '%'.addcslashes($word, '%_\\').'%');
                        }
                    }
                }
            });
        };

        foreach (array_chunk($userIds, 500) as $ids) {
            $profiles = DB::table('user_profiles as up')->whereIn('up.user_id', $ids)
                ->select('up.user_id', 'up.research_interests', 'up.research_visibility', 'up.courses_taught', 'up.courses_visibility', 'up.skills')
                ->get();

            foreach ($profiles as $p) {
                $own = (int) $p->user_id === $viewerId;

                if ($p->research_interests && ($own || in_array($p->research_visibility ?? Visibility::EVERYONE, $allowed, true))) {
                    yield [(int) $p->user_id, 'Research interests', 4, (string) $p->research_interests];
                }

                if ($p->courses_taught && ($own || in_array($p->courses_visibility ?? Visibility::EVERYONE, $allowed, true))) {
                    yield [(int) $p->user_id, 'Courses taught', 2, (string) $p->courses_taught];
                }

                if ($p->skills) {
                    yield [(int) $p->user_id, 'Skills', 1, (string) $p->skills];
                }
            }

            $sections = DB::table('faculty_profile_sections')
                ->whereIn('user_id', $ids)
                ->whereNull('removed_at')
                ->where(fn ($q) => $q->whereIn('visibility', $allowed)->orWhere('user_id', $viewerId))
                ->where(fn ($q) => $like($q, 'body'))
                ->select('user_id', 'title', 'body')
                ->orderBy('position')
                ->cursor();

            foreach ($sections as $s) {
                $title = trim((string) $s->title);

                $weight = match (true) {
                    (bool) preg_match('/research|interest|expertise|focus|area/i', $title) => 4,
                    (bool) preg_match('/course|teach|supervis|thesis|project|biography|profile|overview|about/i', $title) => 2,
                    default => 1,
                };

                yield [(int) $s->user_id, 'Academic profile: '.$title, $weight, (string) $s->body];
            }
        }
    }

    /** The line around a match, without markdown, links or email addresses. */
    private function evidenceLine(string $text, int $offset): string
    {
        $start = strrpos(substr($text, 0, $offset), "\n");
        $end = strpos($text, "\n", $offset);
        $line = substr($text, $start === false ? 0 : $start + 1, ($end === false ? strlen($text) : $end) - ($start === false ? 0 : $start + 1));

        // A single long line (a comma list, a paragraph): keep a window around the match.
        if (strlen($line) > 220) {
            $at = $offset - ($start === false ? 0 : $start + 1);
            $from = max(0, $at - 90);
            $line = ($from > 0 ? '… ' : '').substr($line, $from, 200).(strlen($line) > $from + 200 ? ' …' : '');
        }

        $line = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $line);
        $line = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '', $line);
        $line = preg_replace('/(^|\s)[#>*\-|]+\s*|[*_`|]+/', ' ', $line);

        return mb_substr(trim(preg_replace('/\s+/', ' ', mb_convert_encoding($line, 'UTF-8', 'UTF-8'))), 0, 220);
    }
}
