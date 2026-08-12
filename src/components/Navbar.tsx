import React, { useState, useEffect } from 'react';
import { User, Notification } from '../types/index.ts';
import { fetchNotifications, markNotificationRead } from '../services/api.ts';
import { getRolePermissions } from '../utils/rbac.ts';
import { Bell, Shield, Calendar, ChevronDown, UserCheck, Lock } from 'lucide-react';

interface NavbarProps {
  users: User[];
  currentUser: User;
  onUserChange: (user: User) => void;
  pendingApprovalsCount: number;
  onOpenNewMeeting: () => void;
  onNavigate: (view: string) => void;
  activeView: string;
}

export const Navbar: React.FC<NavbarProps> = ({
  users,
  currentUser,
  onUserChange,
  pendingApprovalsCount,
  onOpenNewMeeting,
  onNavigate,
  activeView
}) => {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [showNotifications, setShowNotifications] = useState(false);
  const [showRoleMenu, setShowRoleMenu] = useState(false);

  const permissions = getRolePermissions(currentUser.role);

  useEffect(() => {
    if (currentUser?.id) {
      loadNotifications();
    }
  }, [currentUser]);

  const loadNotifications = async () => {
    try {
      const data = await fetchNotifications(currentUser.id);
      setNotifications(data);
    } catch (e) {
      console.error(e);
    }
  };

  const handleMarkRead = async (id: string) => {
    await markNotificationRead(id);
    setNotifications(prev => prev.map(n => n.id === id ? { ...n, isRead: true } : n));
  };

  const unreadCount = notifications.filter(n => !n.isRead).length;

  return (
    <header className="sticky top-0 z-30 bg-white border-b border-slate-200 shadow-sm text-slate-900">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        
        {/* Brand & Emblem */}
        <div className="flex items-center space-x-3">
          <div className="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center font-bold text-white shadow-sm">
            <span className="text-lg tracking-wider">UoH</span>
          </div>
          <div>
            <div className="flex items-center space-x-2">
              <h1 className="text-base sm:text-lg font-bold tracking-tight text-slate-900">
                UoH Meeting Management
              </h1>
              <span className="bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">
                {permissions.roleDisplayName}
              </span>
            </div>
            <p className="text-xs text-slate-500 hidden sm:block font-medium">
              University of Haripur • Role-Based Portal Interface
            </p>
          </div>
        </div>

        {/* Quick Actions & Role Switcher */}
        <div className="flex items-center space-x-3">
          
          {/* Quick Schedule Button (Hidden for Faculty) */}
          {permissions.canCreateMeeting ? (
            <button
              onClick={onOpenNewMeeting}
              className="hidden sm:inline-flex items-center space-x-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-2 rounded-xl text-xs font-bold shadow-sm transition-colors cursor-pointer"
            >
              <Calendar className="w-4 h-4" />
              <span>Schedule Meeting</span>
            </button>
          ) : (
            <div className="hidden sm:inline-flex items-center space-x-1 bg-slate-100 text-slate-600 px-3 py-1.5 rounded-xl text-xs font-semibold border border-slate-200">
              <Lock className="w-3.5 h-3.5 text-slate-500" />
              <span>Faculty Access Mode</span>
            </div>
          )}

          {/* Pending Approvals Quick Pill (Visible only to approver roles) */}
          {permissions.canApproveMeetings && (
            <button
              onClick={() => onNavigate('approvals')}
              className={`relative inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border transition-colors ${
                activeView === 'approvals' 
                  ? 'bg-amber-100 border-amber-300 text-amber-900' 
                  : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'
              }`}
            >
              <Shield className="w-3.5 h-3.5 text-amber-600" />
              <span className="hidden md:inline">Approvals</span>
              {pendingApprovalsCount > 0 && (
                <span className="bg-amber-500 text-white font-bold px-1.5 py-0.2 text-[10px] rounded-full">
                  {pendingApprovalsCount}
                </span>
              )}
            </button>
          )}

          {/* Notifications Dropdown */}
          <div className="relative">
            <button
              onClick={() => setShowNotifications(!showNotifications)}
              className="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 relative transition-colors cursor-pointer border border-slate-200"
              title="Notifications"
            >
              <Bell className="w-4 h-4" />
              {unreadCount > 0 && (
                <span className="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center animate-pulse">
                  {unreadCount}
                </span>
              )}
            </button>

            {showNotifications && (
              <div className="absolute right-0 mt-2 w-80 sm:w-96 bg-white border border-slate-200 rounded-2xl shadow-xl z-50 overflow-hidden text-slate-900">
                <div className="p-3 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-slate-700">
                    Notifications ({notifications.length})
                  </h3>
                  <button 
                    onClick={() => setShowNotifications(false)}
                    className="text-xs text-slate-500 hover:text-slate-800 font-semibold"
                  >
                    Close
                  </button>
                </div>
                <div className="max-h-80 overflow-y-auto divide-y divide-slate-100">
                  {notifications.length === 0 ? (
                    <p className="p-4 text-xs text-slate-500 text-center">No notifications</p>
                  ) : (
                    notifications.map(n => (
                      <div 
                        key={n.id}
                        onClick={() => handleMarkRead(n.id)}
                        className={`p-3 text-xs cursor-pointer transition-colors ${
                          n.isRead ? 'bg-white opacity-70' : 'bg-indigo-50/50 hover:bg-indigo-50'
                        }`}
                      >
                        <div className="flex items-start justify-between">
                          <span className="font-bold text-indigo-700">{n.title}</span>
                          <span className="text-[10px] text-slate-400 font-mono">
                            {new Date(n.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                          </span>
                        </div>
                        <p className="text-slate-600 mt-1 leading-relaxed">{n.message}</p>
                      </div>
                    ))
                  )}
                </div>
              </div>
            )}
          </div>

          {/* Role Switcher (MVP Test Feature) */}
          <div className="relative">
            <button
              onClick={() => setShowRoleMenu(!showRoleMenu)}
              className="flex items-center space-x-2 bg-slate-50 hover:bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-xl text-left transition-colors cursor-pointer"
            >
              <img
                src={currentUser.avatar || "https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150"}
                alt={currentUser.name}
                className="w-7 h-7 rounded-full object-cover border border-slate-300"
              />
              <div className="hidden lg:block text-xs">
                <div className="font-bold text-slate-900">{currentUser.name}</div>
                <div className="text-[10px] text-indigo-600 font-bold">{currentUser.role}</div>
              </div>
              <ChevronDown className="w-3.5 h-3.5 text-slate-500" />
            </button>

            {showRoleMenu && (
              <div className="absolute right-0 mt-2 w-72 bg-white border border-slate-200 rounded-2xl shadow-xl z-50 p-2 text-slate-900">
                <div className="px-3 py-2 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                  Switch Role / Persona Test
                </div>
                <div className="max-h-64 overflow-y-auto mt-1 space-y-1">
                  {users.map(u => (
                    <button
                      key={u.id}
                      onClick={() => {
                        onUserChange(u);
                        setShowRoleMenu(false);
                      }}
                      className={`w-full text-left px-3 py-2 rounded-xl text-xs flex items-center justify-between transition-colors ${
                        u.id === currentUser.id 
                          ? 'bg-indigo-50 text-indigo-900 font-bold border border-indigo-200' 
                          : 'hover:bg-slate-50 text-slate-700'
                      }`}
                    >
                      <div>
                        <div className="font-bold text-slate-900">{u.name}</div>
                        <div className="text-[10px] text-slate-500">{u.role} • {u.designation}</div>
                      </div>
                      {u.id === currentUser.id && <UserCheck className="w-4 h-4 text-indigo-600" />}
                    </button>
                  ))}
                </div>
              </div>
            )}
          </div>

        </div>

      </div>
    </header>
  );
};

