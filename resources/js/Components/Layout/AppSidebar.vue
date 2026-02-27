<template>
  <va-sidebar :minimized="minimized" class="app-sidebar" width="250px">
    <va-sidebar-item
      v-for="item in navigationItems"
      :key="item.title"
      :active="isCurrentRoute(item.route)"
    >
      <Link :href="getRouteUrl(item.route)" class="sidebar-link">
        <va-sidebar-item-content>
          <va-icon :name="item.icon" />
          <va-sidebar-item-title v-if="!minimized">{{ item.title }}</va-sidebar-item-title>
        </va-sidebar-item-content>
      </Link>
    </va-sidebar-item>

    <div v-if="!minimized" class="p-4 mt-auto border-t border-gray-300 dark:border-gray-700">
       <span class="text-xs text-gray-500 font-mono font-bold">
         O TEU PERFIL: {{ displayRole }}
       </span>
    </div>
  </va-sidebar>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
  minimized: {
    type: Boolean,
    default: false,
  },
});

const page = usePage();

/**
 * Função reativa extraída diretamente da sessão do utilizador logado para Debug.
 */
const displayRole = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return 'Sem Login / Guest';
    // Lida com o formato em String e com objetos baseados em Enums
    return typeof user.role === 'object' ? user.role.value : user.role;
});

/**
 * Determina os itens de navegação dinamicamente e com muita robustez.
 */
const navigationItems = computed(() => {
  const user = page.props.auth?.user;
  
  // Extração estrita e segura do nível (role)
  let role = null;
  if (user && user.role) {
    role = typeof user.role === 'object' ? user.role.value : user.role;
    if (typeof role === 'string') {
        role = role.toLowerCase(); // Garante que não falha por causa de maiúsculas
    }
  }

  const items = [
    { title: 'Dashboard', icon: 'dashboard', route: 'dashboard' },
    { title: 'Tickets', icon: 'confirmation_number', route: 'tickets.index' },
  ];

  if (role === 'supporter' || role === 'admin') {
    items.push({ title: 'Users', icon: 'group', route: 'users.index' });
    items.push({ title: 'Manage Tags', icon: 'local_offer', route: 'tags.index' });
    items.push({ title: 'Activity Logs', icon: 'history', route: 'activity-logs.index' });
  }

  return items;
});

/**
 * Função segura para gerar o URL da Rota.
 * Se o Laravel não enviar as novas rotas, isto impede o Vue de ficar em branco!
 */
const getRouteUrl = (routeName) => {
    try {
        return route(routeName);
    } catch (error) {
        console.error(`ERRO ZIGGY: A rota '${routeName}' não existe no frontend! Tens de limpar a cache das rotas.`);
        return '#'; 
    }
};

/**
 * Função segura para verificar a rota atual.
 */
const isCurrentRoute = (routeName) => {
  try {
    return route().current(routeName);
  } catch (error) {
    return false;
  }
};
</script>

<style scoped>
.app-sidebar {
  height: calc(100vh - 64px);
  display: flex;
  flex-direction: column;
}
.sidebar-link {
  text-decoration: none;
  color: inherit;
  display: block;
  width: 100%;
}
</style>