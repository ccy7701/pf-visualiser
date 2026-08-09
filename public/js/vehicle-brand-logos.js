const VEHICLE_BRANDS = [
    {
        id: 'proton',
        label: 'Proton',
        logoFile: 'proton.svg',
        models: ['saga', 'persona', 'iriz', 'x50', 'x70', 'x90', 's70', 'exora', 'suprima', 'preve', 'perdana', 'satria', 'wira', 'waja', 'inspira', 'savvy', 'gen 2', 'juara', 'tiara', 'putra'],
    },
    {
        id: 'perodua',
        label: 'Perodua',
        logoFile: 'perodua.svg',
        models: ['myvi', 'axia', 'bezza', 'ativa', 'alza', 'aruz', 'kelisa', 'kancil', 'viva', 'kenari', 'nautica', 'rusa', 'kembara'],
    },
    {
        id: 'mitsubishi',
        label: 'Mitsubishi',
        logoFile: 'mitsubishi.svg',
        models: ['xpander', 'triton', 'pajero', 'outlander', 'lancer', 'attrage', 'asx', 'eclipse cross', 'mirage'],
    },
    {
        id: 'toyota',
        label: 'Toyota',
        logoFile: 'toyota.svg',
        models: ['vios', 'yaris', 'corolla', 'camry', 'altis', 'hilux', 'fortuner', 'innova', 'veloz', 'alphard', 'vellfire', 'avanza', 'rush', 'harrier', 'land cruiser', 'prius', 'gr86', 'supra', 'rav4', 'wish'],
    },
    {
        id: 'honda',
        label: 'Honda',
        logoFile: 'honda.svg',
        models: ['city', 'civic', 'accord', 'jazz', 'fit', 'cr v', 'hr v', 'br v', 'wr v', 'odyssey', 'freed', 'nsx'],
    },
];

function normalizedVehicleName(value) {
    return String(value ?? '')
        .toLocaleLowerCase('en')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

function includesTerm(normalizedName, term) {
    return ` ${normalizedName} `.includes(` ${term} `);
}

export function detectVehicleBrand(vehicleName) {
    const normalizedName = normalizedVehicleName(vehicleName);
    if (!normalizedName) return null;

    const explicitBrand = VEHICLE_BRANDS.find((brand) => includesTerm(normalizedName, brand.id));
    if (explicitBrand) return explicitBrand;

    return VEHICLE_BRANDS.find((brand) => brand.models.some((model) => includesTerm(normalizedName, model))) || null;
}

export { VEHICLE_BRANDS };
