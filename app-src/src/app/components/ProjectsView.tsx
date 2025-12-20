import { useState } from "react";
import { mockProjects, Project } from "../data/mockData";
import { Card } from "./ui/card";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { 
  Briefcase, 
  Search, 
  Plus, 
  Filter,
  FolderOpen,
  Calendar,
  DollarSign,
  FileText
} from "lucide-react";
import { Badge } from "./ui/badge";

const statusColors = {
  planning: "bg-gray-100 text-gray-700",
  proposal: "bg-blue-100 text-blue-700",
  awarded: "bg-green-100 text-green-700",
  implementation: "bg-purple-100 text-purple-700",
  reporting: "bg-orange-100 text-orange-700",
  closed: "bg-slate-100 text-slate-700"
};

export function ProjectsView() {
  const [projects] = useState<Project[]>(mockProjects);
  const [viewMode, setViewMode] = useState<"grid" | "list">("grid");

  return (
    <div className="h-full flex flex-col bg-white">
      {/* Header */}
      <div className="border-b border-gray-200 p-6">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900">Projects</h1>
            <p className="text-gray-600 mt-1">{projects.length} active projects</p>
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline">
              <Filter className="w-4 h-4 mr-2" />
              Filter
            </Button>
            <Button className="bg-blue-600 hover:bg-blue-700">
              <Plus className="w-4 h-4 mr-2" />
              New Project
            </Button>
          </div>
        </div>

        {/* Search */}
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <Input 
            placeholder="Search projects by code, name, or programme..." 
            className="pl-9"
          />
        </div>
      </div>

      {/* Stats Bar */}
      <div className="border-b border-gray-200 bg-gray-50 p-4">
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
          <div className="text-center">
            <div className="text-2xl font-semibold text-gray-900">
              {projects.filter(p => p.status === "proposal").length}
            </div>
            <div className="text-xs text-gray-600 mt-1">In Proposal</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-semibold text-gray-900">
              {projects.filter(p => p.status === "awarded").length}
            </div>
            <div className="text-xs text-gray-600 mt-1">Awarded</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-semibold text-gray-900">
              {projects.filter(p => p.status === "implementation").length}
            </div>
            <div className="text-xs text-gray-600 mt-1">Implementation</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-semibold text-gray-900">
              {projects.filter(p => p.status === "reporting").length}
            </div>
            <div className="text-xs text-gray-600 mt-1">Reporting</div>
          </div>
          <div className="text-center">
            <div className="text-2xl font-semibold text-gray-900">
              {projects.filter(p => p.status === "closed").length}
            </div>
            <div className="text-xs text-gray-600 mt-1">Closed</div>
          </div>
        </div>
      </div>

      {/* Projects Grid */}
      <div className="flex-1 overflow-y-auto p-6">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-w-7xl">
          {projects.map((project) => (
            <Card 
              key={project.id}
              className="p-6 border border-gray-200 hover:shadow-lg transition-all cursor-pointer"
            >
              {/* Header */}
              <div className="flex items-start justify-between mb-4">
                <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                  <Briefcase className="w-5 h-5 text-blue-600" />
                </div>
                <Badge className={statusColors[project.status]}>
                  {project.status}
                </Badge>
              </div>

              {/* Content */}
              <div className="mb-4">
                <div className="font-mono text-xs text-blue-600 mb-1">{project.code}</div>
                <h3 className="font-semibold text-gray-900 mb-2">{project.name}</h3>
                <div className="text-sm text-gray-600">{project.programme}</div>
              </div>

              {/* Info Grid */}
              <div className="space-y-2 mb-4">
                {project.deadline && (
                  <div className="flex items-center gap-2 text-sm text-gray-600">
                    <Calendar className="w-4 h-4 text-gray-400" />
                    <span>Due {project.deadline}</span>
                  </div>
                )}
                <div className="flex items-center gap-2 text-sm text-gray-600">
                  <DollarSign className="w-4 h-4 text-gray-400" />
                  <span>{project.budget}</span>
                </div>
                <div className="flex items-center gap-2 text-sm text-gray-600">
                  <FileText className="w-4 h-4 text-gray-400" />
                  <span>{project.documents} documents</span>
                </div>
              </div>

              {/* Actions */}
              <div className="pt-4 border-t border-gray-200">
                <Button variant="outline" size="sm" className="w-full">
                  <FolderOpen className="w-4 h-4 mr-2" />
                  Open Project
                </Button>
              </div>
            </Card>
          ))}
        </div>
      </div>
    </div>
  );
}
