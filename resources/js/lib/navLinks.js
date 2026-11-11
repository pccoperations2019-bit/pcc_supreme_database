export default {
  left: [
    {
      label: 'Profiles',
      route: 'profiles.index',
      icon: 'lucide:users',
      children: [
        { label: 'Centers', route: 'centers.index' },
        { label: 'Partners', route: 'partners.index' },
        { label: 'Farmers', route: 'farmers.index' },
      ],
    },
    {
      // A sibling of Profiles, not a child: that menu holds farmer and partner
      // profiles, which are a different entity from user accounts.
      label: 'Users',
      route: 'users.index',
      icon: 'lucide:user-cog',
      permission: 'view-users',
    },
    {
      label: 'PAPs',
      route: '#',
      icon: 'lucide:clipboard-list',
      children: [
        //{ label: 'Carabao-Based Enterprise Development', route: '#' },
        {
          label: 'Carabao-Based Business Improvement Network',
          route: 'cbin-project-sites.index',
        },
        {
          label: 'Coconut-Carabao Development Project',
          route: 'ccdp-project-sites.index',
        },
        { label: 'PL 480', route: 'pl480.index', permission: 'view-reports' },
        //{ label: 'Carabao Herd Builp Up', route: '#' },
        {
          label: 'Enhanced Partnership Against Hunger and Poverty',
          route: 'epahp.index',
          permission: 'view-reports',
        },
        // { label: 'Milk Feeding Program', route: '#' },
        { label: 'Dairy Box', route: 'dairy-boxes.index' },
        { label: 'Projects', route: 'projects.index' },
      ],
    },
    {
      label: 'Services',
      route: 'services.index',
      icon: 'lucide:hand-heart',
      children: [
        { label: 'Production Support', route: 'production-support.index' },
        { label: 'Market Development', route: 'market-development.index' },
        { label: 'Information, Education, and Communication', route: 'iec.index' },
        { label: 'Extension Support, Education, and Training Services', route: 'eset.index' },
      ],
    },
    {
      label: 'Reports',
      route: '#',
      icon: 'lucide:chart-area',
      children: [
        { label: 'Summary of Buffalo Derived Products', route: 'summary-buffalo-derived-products' },
        { label: 'Milk Tracker', route: 'milk-productions.index' },
        { label: 'Manure Production', route: 'manure-productions.index' },
        { label: 'Meat Production', route: 'meat-productions.index' },
        { label: 'Milk Utilization', route: 'reports.milk-utilization' },
        //{ label: 'PSA', route: '#' },
        { label: 'Milk Feeding Programs', route: 'milk-feeding-programs.index' },
        { label: 'Center Production Goals', route: 'center-production-goals.index' },
        {
          label: 'Beneficiary Sources',
          route: 'beneficiary-sources.index',
          permission: 'view-beneficiary-sources',
        },
        {
          label: 'Monthly Tasks',
          route: 'monthly-tasks.index',
          icon: 'lucide:list-checks',
          permission: 'view-monthly-tasks',
        },
        {
          label: 'Audit Trail',
          route: 'audits.index',
          permission: 'view-audits',
        },
        { label: 'GENERATE', route: 'reports.generate' },
      ],
    },
  ],
  right: [{ label: 'Documents', route: 'documents.index', icon: 'lucide:file-up' }],
}
