/**
 * The estimate scale offered when sizing an issue. Anything else can still arrive through the API,
 * so pages should keep showing an off-scale value once it is set.
 */
export const pointScale = [1, 2, 3, 5, 8, 13, 21];

export const pointOptions = (current: number | null) =>
    [...new Set(current === null ? pointScale : [...pointScale, current])]
        .sort((a, b) => a - b)
        .map((points) => ({ value: String(points), label: String(points) }));
