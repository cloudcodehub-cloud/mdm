export type MobileNavDestination = {
    key: string;
    title: string;
    href: string;
};

export type MobileNavState = {
    home: MobileNavDestination;
    shortcuts: MobileNavDestination[];
    more: MobileNavDestination[];
    available: MobileNavDestination[];
    defaults: string[];
};
