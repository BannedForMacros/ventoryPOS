import React from 'react';
import { usePage } from '@inertiajs/react';
import { Toaster } from 'react-hot-toast';
import RouterLoadingOverlay from '@/Components/RouterLoadingOverlay';
import type { PageProps } from '@/types';

interface PosLayoutProps {
    children: React.ReactNode;
}

export default function PosLayout({ children }: PosLayoutProps) {
    return (
        <div className="h-screen w-screen overflow-hidden flex flex-col" style={{ backgroundColor: 'var(--color-bg, #f3f4f6)' }}>
            <RouterLoadingOverlay />
            <Toaster position="top-right" />
            {children}
        </div>
    );
}
