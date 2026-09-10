import {
    createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode,
} from 'react';
import { api } from './api';
import { toFailure } from '../lib/http';
import type { Establishment, User } from './types';

interface AppState {
    user: User | null;
    establishments: Establishment[];
    current: Establishment | null;
    booting: boolean;
    setUser: (user: User | null) => void;
    selectEstablishment: (id: number) => void;
    refreshEstablishments: () => Promise<Establishment[]>;
    signOut: () => Promise<void>;
}

const AppContext = createContext<AppState | null>(null);
const CURRENT_KEY = 'cardapio:establishment';

export function AppProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [establishments, setEstablishments] = useState<Establishment[]>([]);
    const [currentId, setCurrentId] = useState<number | null>(() => {
        const stored = Number(localStorage.getItem(CURRENT_KEY));

        return Number.isFinite(stored) && stored > 0 ? stored : null;
    });
    const [booting, setBooting] = useState(true);

    const refreshEstablishments = useCallback(async () => {
        const list = await api.establishments();
        setEstablishments(list);

        return list;
    }, []);

    // Sessão já existente: restaura o usuário ao abrir o painel.
    useEffect(() => {
        let active = true;

        api.me()
            .then(async (loaded) => {
                if (!active) return;

                setUser(loaded);
                await refreshEstablishments();
            })
            .catch(() => {
                if (active) setUser(null);
            })
            .finally(() => {
                if (active) setBooting(false);
            });

        return () => {
            active = false;
        };
    }, [refreshEstablishments]);

    const selectEstablishment = useCallback((id: number) => {
        setCurrentId(id);
        localStorage.setItem(CURRENT_KEY, String(id));
    }, []);

    const current = useMemo(() => {
        if (establishments.length === 0) return null;

        return establishments.find((item) => item.id === currentId) ?? establishments[0]!;
    }, [establishments, currentId]);

    const signOut = useCallback(async () => {
        try {
            await api.logout();
        } catch (error) {
            // Sessão já expirada não deve impedir a saída da interface.
            if (toFailure(error).status !== 401) throw error;
        }

        setUser(null);
        setEstablishments([]);
        localStorage.removeItem(CURRENT_KEY);
    }, []);

    const value = useMemo<AppState>(() => ({
        user,
        establishments,
        current,
        booting,
        setUser,
        selectEstablishment,
        refreshEstablishments,
        signOut,
    }), [user, establishments, current, booting, selectEstablishment, refreshEstablishments, signOut]);

    return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}

export function useApp(): AppState {
    const context = useContext(AppContext);

    if (!context) throw new Error('useApp precisa estar dentro de <AppProvider>.');

    return context;
}

/** Estabelecimento atual garantido — usado nas páginas internas do painel. */
export function useEstablishment(): Establishment {
    const { current } = useApp();

    if (!current) throw new Error('Nenhum estabelecimento selecionado.');

    return current;
}
