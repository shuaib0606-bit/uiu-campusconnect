/**
 * Photo when the person has one, initials on a terracotta chip when they do
 * not. Mirrors the Blade user-avatar component.
 */
export default function UserAvatar({
    user = null,
    url = null,
    initials = null,
    size = 'h-10 w-10',
    text = 'text-sm',
    className = '',
}) {
    const photo = url ?? user?.avatar_url ?? null;
    const label = initials ?? user?.avatar_initials ?? '';

    const base = `grid shrink-0 place-items-center rounded-full bg-cover bg-center font-semibold ${size} ${text} ${className}`;

    if (photo) {
        return <span className={base} style={{ backgroundImage: `url('${photo}')` }} />;
    }

    return (
        <span className={`${base} bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-200`}>
            {label}
        </span>
    );
}
