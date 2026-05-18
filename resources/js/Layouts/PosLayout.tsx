import React from 'react';
import { Toaster } from 'react-hot-toast';
import RouterLoadingOverlay from '@/Components/RouterLoadingOverlay';

interface PosLayoutProps {
    children: React.ReactNode;
}

export default function PosLayout({ children }: PosLayoutProps) {
    return (
        <div
            className="flex flex-col w-screen overflow-hidden"
            style={{
                height: '100dvh',
                backgroundColor: 'var(--color-bg, #f3f4f6)',
                paddingTop: 'env(safe-area-inset-top)',
                paddingLeft: 'env(safe-area-inset-left)',
                paddingRight: 'env(safe-area-inset-right)',
                overscrollBehavior: 'contain',
            }}
        >
            <RouterLoadingOverlay />
            <Toaster
                position="top-center"
                toastOptions={{
                    duration: 2500,
                    style: {
                        fontSize: '13px',
                        padding: '10px 14px',
                        borderRadius: '12px',
                    },
                }}
            />
            {children}
        </div>
    );
}
