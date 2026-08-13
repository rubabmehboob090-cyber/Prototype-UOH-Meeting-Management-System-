import React from 'react';
import { 
  LayoutDashboard, PlusCircle, CheckSquare, Calendar, Building, 
  ListTodo, FileText, BarChart3, History, ShieldAlert, Lock 
} from 'lucide-react';
import { User } from '../types/index';
import { getRolePermissions } from '../utils/rbac';

interface SidebarProps {
  currentUser: User;
  activeView: string;
  onNavigate: (view: string) => void;
  pendingApprovalsCount: number;
}

export const Sidebar: React.FC<SidebarProps> = ({
  currentUser,
  activeView,
  onNavigate,
  pendingApprovalsCount
}) => {
  const permissions = getRolePermissions(currentUser.role);

  const allMenuItems = [
    { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { id: 'schedule', label: 'Request Meeting', icon: PlusCircle, badge: 'New' },
    { 
      id: 'approvals', 
      label: 'Approval Queue', 
      icon: CheckSquare, 
      count: pendingApprovalsCount 
    },
    { id: 'calendar', label: 'Master Calendar', icon: Calendar },
    { id: 'rooms', label: 'Room Management', icon: Building },
    { id: 'action-items', label: 'Action Items Tracker', icon: ListTodo },
    { id: 'minutes', label: 'Minutes & Agendas', icon: FileText },
    { id: 'reports', label: 'Utilization & Reports', icon: BarChart3 },
    { id: 'audit-logs', label: 'System Audit Logs', icon: History }
  ];

  // Filter menu items allowed for the current role
  const visibleMenuItems = allMenuItems.filter(item => 
    permissions.allowedViews.includes(item.id)
  );

  return (
    <aside className="w-full md:w-64 bg-white border-r border-slate-200 flex-shrink-0 min-h-[calc(100vh-4rem)] p-4 shadow-sm">
      <div className="space-y-1">
        
        {/* Role Abstraction Tag */}
        <div className="px-3 py-2 bg-indigo-50 border border-indigo-100 rounded-xl mb-3">
          <div className="text-[10px] font-bold uppercase tracking-wider text-indigo-700 flex items-center justify-between">
            <span>{permissions.roleDisplayName}</span>
            <Lock className="w-3 h-3 text-indigo-500" />
          </div>
          <p className="text-[10px] text-slate-500 font-medium mt-0.5 line-clamp-1">
            {currentUser.designation}
          </p>
        </div>

        <div className="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
          UoH Navigation
        </div>

        {visibleMenuItems.map((item) => {
          const Icon = item.icon;
          const isActive = activeView === item.id;
          return (
            <button
              key={item.id}
              onClick={() => onNavigate(item.id)}
              className={`w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all cursor-pointer ${
                isActive
                  ? 'bg-indigo-600 text-white shadow-sm'
                  : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              }`}
            >
              <div className="flex items-center space-x-3">
                <Icon className={`w-4 h-4 ${isActive ? 'text-white' : 'text-slate-500'}`} />
                <span>{item.label}</span>
              </div>
              {item.count !== undefined && item.count > 0 && (
                <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                  isActive ? 'bg-white text-indigo-700' : 'bg-amber-500 text-white'
                }`}>
                  {item.count}
                </span>
              )}
              {item.badge && (
                <span className={`px-1.5 py-0.2 text-[9px] rounded font-bold uppercase ${
                  isActive ? 'bg-indigo-500 text-white' : 'bg-indigo-50 text-indigo-700 border border-indigo-200'
                }`}>
                  {item.badge}
                </span>
              )}
            </button>
          );
        })}
      </div>

      {/* University Haripur Contact / Help Footer */}
      <div className="mt-8 pt-4 border-t border-slate-100 px-3 text-slate-500 text-[11px]">
        <div className="flex items-center space-x-1.5 font-bold text-slate-800">
          <ShieldAlert className="w-3.5 h-3.5 text-indigo-600" />
          <span>Registrar Office UoH</span>
        </div>
        <p className="mt-1 text-[10px] text-slate-500 leading-normal">
          For meeting room keys or IT support, contact Ext. 601 / Ext. 612.
        </p>
      </div>
    </aside>
  );
};

