import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <rect width="32" height="32" rx="8" fill="currentColor" />
            <path
                d="M8.5 22.5V9.5h3.1l4.4 8.4 4.4-8.4h3.1v13H20.4v-7.6l-3.3 6.2h-2.2l-3.3-6.2v7.6H8.5Z"
                fill="white"
            />
        </svg>
    );
}
