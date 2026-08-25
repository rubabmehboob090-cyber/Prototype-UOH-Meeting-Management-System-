import React from 'react';
import { ConflictCheckResult, SmartSuggestion } from '../types/index';
import { AlertTriangle, CheckCircle2, Clock, MapPin, Sparkles, UserX, Calendar, Users, Wifi, AlertOctagon, Info } from 'lucide-react';

interface ConflictWarningProps {
  conflictResult: ConflictCheckResult;
  onApplySuggestion: (suggestion: SmartSuggestion) => void;
}

export const ConflictWarning: React.FC<ConflictWarningProps> = ({
  conflictResult,
  onApplySuggestion
}) => {
  if (!conflictResult.hasConflict && conflictResult.conflicts.length === 0) {
    return (
      <div className="bg-emerald-50 border border-emerald-200 rounded-2xl p-3.5 text-emerald-900 text-xs flex items-center space-x-2.5">
        <CheckCircle2 className="w-5 h-5 text-emerald-600 flex-shrink-0" />
        <div>
          <span className="font-bold text-emerald-900">Zero Schedule Conflicts Detected:</span>
          <span className="ml-1 text-emerald-800">
            Selected venue, time slot, and participant rosters are 100% free and ready to schedule.
          </span>
        </div>
      </div>
    );
  }

  const isCritical = conflictResult.conflicts.some(c => c.severity === 'critical' || c.type === 'room' || c.type === 'participant');

  return (
    <div className={`rounded-2xl p-4 text-xs space-y-3 border ${
      isCritical ? 'bg-rose-50/70 border-rose-200' : 'bg-amber-50/70 border-amber-200'
    }`}>
      {/* Header */}
      <div className="flex items-start space-x-2.5">
        {isCritical ? (
          <AlertOctagon className="w-5 h-5 text-rose-600 flex-shrink-0 mt-0.5" />
        ) : (
          <AlertTriangle className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
        )}
        <div className="flex-1">
          <div className="flex items-center justify-between">
            <h4 className={`font-bold text-sm ${isCritical ? 'text-rose-900' : 'text-amber-900'}`}>
              Automatic Clash Engine: {conflictResult.conflicts.length} Constraint{conflictResult.conflicts.length > 1 ? 's' : ''} Identified
            </h4>
            <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
              isCritical ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-amber-100 text-amber-800 border border-amber-300'
            }`}>
              {isCritical ? 'Hard Conflict' : 'Advisory Warning'}
            </span>
          </div>
          <p className={`mt-0.5 font-medium ${isCritical ? 'text-rose-700' : 'text-amber-800'}`}>
            Overlapping room reservations, participant schedule bottlenecks, or venue constraints were automatically detected.
          </p>
        </div>
      </div>

      {/* Conflicts List */}
      <div className="space-y-2">
        {conflictResult.conflicts.map((conf, idx) => {
          const isItemCritical = conf.severity === 'critical' || conf.type === 'room' || conf.type === 'participant';
          return (
            <div 
              key={idx} 
              className={`bg-white border rounded-xl p-3 space-y-1 shadow-xs ${
                isItemCritical ? 'border-rose-200' : 'border-amber-200'
              }`}
            >
              <div className="flex items-center justify-between">
                <span className={`font-bold flex items-center space-x-1.5 ${
                  isItemCritical ? 'text-rose-900' : 'text-amber-900'
                }`}>
                  {conf.type === 'room' && <MapPin className="w-3.5 h-3.5 text-rose-600" />}
                  {conf.type === 'participant' && <UserX className="w-3.5 h-3.5 text-rose-600" />}
                  {conf.type === 'university_event' && <Calendar className="w-3.5 h-3.5 text-rose-600" />}
                  {conf.type === 'capacity' && <Users className="w-3.5 h-3.5 text-amber-600" />}
                  {conf.type === 'facility' && <Wifi className="w-3.5 h-3.5 text-indigo-600" />}
                  {conf.type === 'buffer' && <Clock className="w-3.5 h-3.5 text-amber-600" />}
                  {conf.type === 'maintenance' && <AlertOctagon className="w-3.5 h-3.5 text-rose-600" />}
                  <span>{conf.title}</span>
                </span>
                {conf.existingTime && (
                  <span className="text-[10px] bg-slate-100 text-slate-800 border border-slate-200 px-2 py-0.5 rounded-lg font-mono font-bold">
                    {conf.existingTime}
                  </span>
                )}
              </div>
              <p className="text-slate-600 text-[11px] leading-relaxed">{conf.description}</p>
            </div>
          );
        })}
      </div>

      {/* Smart Availability Engine Suggestions */}
      {conflictResult.suggestions && conflictResult.suggestions.length > 0 && (
        <div className="mt-3 pt-3 border-t border-slate-200/80 space-y-2">
          <div className="flex items-center space-x-1.5 text-indigo-700 font-bold text-xs">
            <Sparkles className="w-4 h-4 text-indigo-600" />
            <span>Automatic Conflict Resolution Suggestions (1-Click Auto-Fit)</span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            {conflictResult.suggestions.map((sug, i) => (
              <div 
                key={i} 
                className="bg-white border border-indigo-200 hover:border-indigo-400 rounded-xl p-3 flex flex-col justify-between space-y-2 shadow-xs transition-colors"
              >
                <div>
                  <div className="font-bold text-slate-900 flex items-center justify-between">
                    <span>{sug.roomName}</span>
                    <span className="text-[10px] font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-lg border border-indigo-100">
                      Cap: {sug.capacity} seats
                    </span>
                  </div>
                  <div className="text-[10px] text-slate-500 font-medium flex items-center space-x-1 mt-1">
                    <Clock className="w-3 h-3 text-indigo-600" />
                    <span>{sug.date} • {sug.startTime} - {sug.endTime}</span>
                  </div>
                  <p className="text-[10px] text-slate-600 mt-1 italic leading-tight">{sug.reason}</p>
                </div>

                <button
                  type="button"
                  onClick={() => onApplySuggestion(sug)}
                  className="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold py-1.5 rounded-xl shadow-xs transition-colors cursor-pointer flex items-center justify-center space-x-1"
                >
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  <span>Apply This Slot</span>
                </button>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

