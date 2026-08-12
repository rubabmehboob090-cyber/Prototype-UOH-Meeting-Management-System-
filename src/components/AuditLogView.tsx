import React, { useState, useEffect } from 'react';
import { AuditLog } from '../types/index.ts';
import { fetchAuditLogs } from '../services/api.ts';
import { History } from 'lucide-react';

export const AuditLogView: React.FC = () => {
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadLogs();
  }, []);

  const loadLogs = async () => {
    setLoading(true);
    try {
      const data = await fetchAuditLogs();
      setLogs(data);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6 text-slate-900">
      
      {/* Header */}
      <div className="flex items-center justify-between border-b border-slate-200 pb-5">
        <div>
          <div className="flex items-center space-x-2">
            <div className="p-2 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100">
              <History className="w-5 h-5" />
            </div>
            <h2 className="text-lg font-bold tracking-tight text-slate-900">System Administrative Audit Trail</h2>
          </div>
          <p className="text-xs text-slate-500 mt-1">
            Audit trail of meeting requests, statutory approvals, room reservations, and minutes publishing.
          </p>
        </div>
      </div>

      {loading ? (
        <div className="text-center py-12 text-slate-500 text-xs font-medium">Loading audit records...</div>
      ) : (
        <div className="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
          <div className="divide-y divide-slate-100">
            {logs.map((log) => (
              <div key={log.id} className="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/80 text-xs transition-colors">
                <div className="space-y-1">
                  <div className="flex items-center space-x-2">
                    <span className="font-bold text-indigo-700">{log.action}</span>
                    <span className="text-[10px] bg-slate-50 text-slate-600 border border-slate-200 px-2 py-0.5 rounded-md font-mono font-medium">
                      IP: {log.ipAddress}
                    </span>
                  </div>
                  <p className="text-slate-700 leading-relaxed font-medium">{log.details}</p>
                </div>

                <div className="text-left sm:text-right text-[11px] text-slate-500 flex-shrink-0">
                  <div className="font-bold text-slate-900">{log.userName}</div>
                  <div className="text-[10px] font-mono text-slate-500 mt-0.5">
                    {new Date(log.timestamp).toLocaleString()}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

    </div>
  );
};

