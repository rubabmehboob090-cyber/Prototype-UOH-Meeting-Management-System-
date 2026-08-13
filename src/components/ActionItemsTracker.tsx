import React, { useState, useEffect } from 'react';
import { ActionItem, User, Meeting } from '../types/index';
import { fetchActionItems, updateActionItemStatus } from '../services/api';
import { ListTodo, CheckCircle2, Clock, AlertCircle, Filter, UserCheck } from 'lucide-react';

interface ActionItemsTrackerProps {
  users: User[];
  meetings: Meeting[];
  currentUser: User;
}

export const ActionItemsTracker: React.FC<ActionItemsTrackerProps> = ({ users, meetings, currentUser }) => {
  const [items, setItems] = useState<ActionItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [filterAssignee, setFilterAssignee] = useState<string>('all');
  const [filterStatus, setFilterStatus] = useState<string>('all');

  useEffect(() => {
    loadItems();
  }, []);

  const loadItems = async () => {
    setLoading(true);
    try {
      const data = await fetchActionItems();
      setItems(data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const handleStatusChange = async (id: string, newStatus: string) => {
    try {
      await updateActionItemStatus(id, newStatus);
      setItems((prev) => prev.map((item) => (item.id === id ? { ...item, status: newStatus as any } : item)));
    } catch (e) {
      console.error(e);
    }
  };

  const filteredItems = items.filter((item) => {
    if (filterAssignee !== 'all' && item.assigneeId !== filterAssignee) return false;
    if (filterStatus !== 'all' && item.status !== filterStatus) return false;
    return true;
  });

  return (
    <div className="space-y-6 text-slate-900">
      
      {/* Header */}
      <div className="flex items-center justify-between border-b border-slate-200 pb-4">
        <div>
          <div className="flex items-center space-x-2">
            <ListTodo className="w-6 h-6 text-indigo-600" />
            <h2 className="text-xl font-bold tracking-tight text-slate-900">Post-Meeting Action Items Tracker</h2>
          </div>
          <p className="text-xs text-slate-500 mt-1">
            Track assignees, deadlines, priorities, and implementation progress for decisions made in university meetings.
          </p>
        </div>
      </div>

      {/* Filter Bar */}
      <div className="bg-white border border-slate-200 rounded-2xl p-4 flex flex-wrap items-center gap-3 text-xs shadow-sm">
        <div className="flex items-center space-x-2">
          <Filter className="w-4 h-4 text-slate-500" />
          <span className="font-bold text-slate-700">Filters:</span>
        </div>

        <select
          value={filterAssignee}
          onChange={(e) => setFilterAssignee(e.target.value)}
          className="bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3 py-1.5 focus:outline-none focus:border-indigo-500 font-medium"
        >
          <option value="all">All Assignees</option>
          {users.map((u) => (
            <option key={u.id} value={u.id}>{u.name} ({u.role})</option>
          ))}
        </select>

        <select
          value={filterStatus}
          onChange={(e) => setFilterStatus(e.target.value)}
          className="bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-3 py-1.5 focus:outline-none focus:border-indigo-500 font-medium"
        >
          <option value="all">All Statuses</option>
          <option value="Pending">Pending</option>
          <option value="In Progress">In Progress</option>
          <option value="Completed">Completed</option>
        </select>

        <button
          onClick={() => setFilterAssignee(currentUser.id)}
          className="ml-auto bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-3.5 py-1.5 rounded-xl font-bold cursor-pointer transition-colors"
        >
          My Tasks Only
        </button>
      </div>

      {/* Task Cards Grid */}
      {loading ? (
        <div className="text-center py-12 text-slate-500 text-xs font-medium">Loading action items...</div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {filteredItems.map((act) => {
            const assignee = users.find((u) => u.id === act.assigneeId);
            const mtg = meetings.find((m) => m.id === act.meetingId);

            return (
              <div 
                key={act.id} 
                className="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-3 flex flex-col justify-between"
              >
                <div>
                  <div className="flex items-start justify-between">
                    <div>
                      <h3 className="text-sm font-bold text-slate-900">{act.title}</h3>
                      <p className="text-[10px] text-slate-500 mt-0.5">
                        Origin Meeting: <strong className="text-slate-700">{mtg?.title || 'Statutory Session'}</strong>
                      </p>
                    </div>

                    <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                      act.priority === 'High' ? 'bg-rose-100 text-rose-800 border border-rose-200' :
                      act.priority === 'Medium' ? 'bg-amber-100 text-amber-800 border border-amber-200' :
                      'bg-slate-100 text-slate-700'
                    }`}>
                      {act.priority} Priority
                    </span>
                  </div>

                  <div className="mt-3 bg-slate-50 p-2.5 rounded-xl border border-slate-200 flex items-center justify-between text-xs">
                    <div className="flex items-center space-x-2">
                      <img
                        src={assignee?.avatar}
                        alt={assignee?.name}
                        className="w-6 h-6 rounded-full object-cover border border-slate-300"
                      />
                      <div>
                        <div className="font-bold text-slate-800">{assignee?.name}</div>
                        <div className="text-[9px] text-slate-500">{assignee?.designation}</div>
                      </div>
                    </div>

                    <div className="text-right text-[10px] font-mono font-bold text-indigo-600">
                      Due: {act.deadline}
                    </div>
                  </div>
                </div>

                <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                  <span className="text-[10px] text-slate-500 font-medium">Update Status:</span>
                  <div className="flex items-center space-x-1">
                    {(['Pending', 'In Progress', 'Completed'] as const).map((st) => (
                      <button
                        key={st}
                        onClick={() => handleStatusChange(act.id, st)}
                        className={`px-2.5 py-1 rounded-lg text-[10px] font-bold transition-colors cursor-pointer ${
                          act.status === st 
                            ? st === 'Completed' ? 'bg-green-600 text-white' :
                              st === 'In Progress' ? 'bg-amber-500 text-white' :
                              'bg-indigo-600 text-white'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        }`}
                      >
                        {st}
                      </button>
                    ))}
                  </div>
                </div>

              </div>
            );
          })}
        </div>
      )}

    </div>
  );
};
