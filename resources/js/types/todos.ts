export type Priority = 'low' | 'normal' | 'high';
export type ListType = 'master' | 'daily' | 'custom';
export type SortMode = 'manual' | 'created_at' | 'alphabetical' | 'priority';

export type Tag = {
    id: number;
    name: string;
    color: string | null;
};

export type ListMembership = {
    id: number;
    type: ListType;
    label: string;
};

export type SubTodo = {
    id: number;
    title: string;
    completed_at: string | null;
    position: number;
};

export type Todo = {
    id: number;
    title: string;
    description: string | null;
    priority: Priority;
    completed_at: string | null;
    created_at: string;
    tags: Tag[];
    position?: number;
    list_memberships?: ListMembership[];
    sub_todos?: SubTodo[];
};

export type TodoList = {
    id: number;
    type: ListType;
    name: string | null;
    date: string | null;
    sort_mode: SortMode;
};

export type SidebarLists = {
    master: { id: number; href: string };
    today: { id: number | null; date: string; href: string };
    customs: Array<{ id: number; name: string; href: string }>;
    upcomingDailies: Array<{ id: number; date: string; href: string }>;
};
