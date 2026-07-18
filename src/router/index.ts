import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
    },
    {
      path: '/workspace/:projectId',
      name: 'workspace',
      component: () => import('@/views/WorkspaceView.vue'),
      props: true,
    },
  ],
})

export default router
