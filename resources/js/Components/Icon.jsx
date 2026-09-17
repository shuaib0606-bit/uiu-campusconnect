import { ICON_PATHS } from './icons';

/** Same stroke settings as the Blade ui-icon component, so the two look alike. */
export default function Icon({ name, className = 'h-5 w-5', ...rest }) {
    const path = ICON_PATHS[name] ?? ICON_PATHS['question-mark-circle'];

    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
            aria-hidden="true"
            {...rest}
        >
            <path d={path} />
        </svg>
    );
}
