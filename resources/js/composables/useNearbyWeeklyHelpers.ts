export function getInterestEmoji(slug: string): string {
    const map: Record<string, string> = {
        music: '🎵',
        concerts: '🎵',
        food: '🍽️',
        'food-drink': '🍽️',
        'food-and-drink': '🍽️',
        arts: '🎨',
        art: '🎨',
        'arts-and-culture': '🎨',
        sports: '⚽',
        sport: '⚽',
        comedy: '😂',
        theatre: '🎭',
        theater: '🎭',
        film: '🎬',
        cinema: '🎬',
        movies: '🎬',
        outdoors: '🌿',
        outdoor: '🌿',
        nature: '🌿',
        hiking: '🌿',
        family: '👨‍👩‍👧',
        'family-days-out': '👨‍👩‍👧',
        nightlife: '🌙',
        fitness: '💪',
        wellness: '🧘',
        tech: '💻',
        technology: '💻',
        markets: '🛍️',
        market: '🛍️',
        shopping: '🛍️',
        culture: '🏛️',
        dance: '💃',
        gaming: '🎮',
        games: '🎮',
        books: '📚',
        literature: '📚',
        charity: '❤️',
        networking: '🤝',
        photography: '📷',
        science: '🔬',
        travel: '✈️',
        festivals: '🎪',
        festival: '🎪',
        exhibitions: '🖼️',
        exhibition: '🖼️',
        workshops: '🛠️',
        workshop: '🛠️',
        'farming-and-rural': '🌾',
    };
    return map[slug] ?? '✨';
}

export function radiusDescription(radius: number): string {
    if (radius <= 5) return 'Around the corner';
    if (radius <= 10) return 'Close to home';
    if (radius <= 25) return 'Easy evening radius';
    if (radius <= 50) return 'Broader weekend plans';
    return 'Big day-out range';
}
