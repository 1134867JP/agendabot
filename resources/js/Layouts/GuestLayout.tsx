import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-[100dvh] flex-col items-center bg-gray-100 px-4 py-6 sm:justify-center sm:py-8">
            <div>
                <Link href="/">
                    <ApplicationLogo className="h-20 w-20 fill-current text-gray-500" />
                </Link>
            </div>

            <div className="mt-5 w-full max-w-md overflow-hidden rounded-2xl bg-white px-5 py-5 shadow-md sm:mt-6 sm:px-6 sm:py-6">
                {children}
            </div>
        </div>
    );
}
