import { useEffect, useRef, useState } from 'react';

export function useChartSize(height: number) {
    const ref = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(0);

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        const update = () => {
            const nextWidth = Math.floor(element.getBoundingClientRect().width);

            if (nextWidth > 0) {
                setWidth(nextWidth);
            }
        };

        update();

        const observer = new ResizeObserver(update);
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return { ref, width, height };
}
